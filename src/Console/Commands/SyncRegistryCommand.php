<?php

namespace Nawasara\Cloudflare\Console\Commands;

use Illuminate\Console\Command;
use Nawasara\Cloudflare\Services\CloudflareClient;
use Nawasara\Cloudflare\Services\DnsRegistrySync;
use Nawasara\Cloudflare\Services\ZoneRegistrySync;

class SyncRegistryCommand extends Command
{
    protected $signature = 'cloudflare:sync-registry
                            {--zones-only : Only sync zones, skip DNS records}
                            {--dns-only : Only sync DNS records, skip zones}';

    protected $description = 'Sync Cloudflare zones and DNS records into the registry. Detects new, updated, and deleted records.';

    public function handle(
        CloudflareClient $cloudflare,
        ZoneRegistrySync $zoneSync,
        DnsRegistrySync $dnsSync,
    ): int {
        $zonesOnly = (bool) $this->option('zones-only');
        $dnsOnly = (bool) $this->option('dns-only');

        if (! $dnsOnly) {
            $this->info('Syncing zones...');
            $zoneStats = $zoneSync->sync();
            $this->table(
                ['total', 'created', 'linked', 'updated', 'unchanged', 'deactivated'],
                [[
                    $zoneStats['total'],
                    $zoneStats['created'],
                    $zoneStats['linked'],
                    $zoneStats['updated'],
                    $zoneStats['unchanged'],
                    $zoneStats['deactivated'],
                ]]
            );

            if ($zoneStats['created'] > 0) {
                $this->warn("→ {$zoneStats['created']} zone baru terdeteksi");
            }
            if ($zoneStats['deactivated'] > 0) {
                $this->warn("→ {$zoneStats['deactivated']} zone dihapus dari Cloudflare (mark inactive)");
            }
        }

        if (! $zonesOnly) {
            $zones = $cloudflare->getCachedZones();
            $this->info("\nSyncing DNS records for " . count($zones) . " zone(s)...");

            $totals = ['total' => 0, 'created' => 0, 'linked' => 0, 'updated' => 0, 'unchanged' => 0, 'skipped' => 0, 'deactivated' => 0];

            foreach ($zones as $zone) {
                $stats = $dnsSync->syncZone($zone['id']);
                foreach ($totals as $k => $_) {
                    $totals[$k] += $stats[$k] ?? 0;
                }
                if ($stats['created'] > 0 || $stats['deactivated'] > 0) {
                    $delta = [];
                    if ($stats['created'] > 0) $delta[] = "+{$stats['created']}";
                    if ($stats['deactivated'] > 0) $delta[] = "-{$stats['deactivated']}";
                    $this->line("  {$zone['name']}: " . implode(' ', $delta));
                }
            }

            $this->table(
                ['total', 'created', 'linked', 'updated', 'unchanged', 'skipped', 'deactivated'],
                [[
                    $totals['total'],
                    $totals['created'],
                    $totals['linked'],
                    $totals['updated'],
                    $totals['unchanged'],
                    $totals['skipped'],
                    $totals['deactivated'],
                ]]
            );

            if ($totals['created'] > 0) {
                $this->warn("→ {$totals['created']} DNS record baru terdeteksi (perlu review OPD/PIC)");
            }
            if ($totals['deactivated'] > 0) {
                $this->warn("→ {$totals['deactivated']} DNS record dihapus dari Cloudflare (mark inactive)");
            }
        }

        $this->info("\nDone.");
        return self::SUCCESS;
    }
}
