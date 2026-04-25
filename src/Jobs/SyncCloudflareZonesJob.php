<?php

namespace Nawasara\Cloudflare\Jobs;

use Nawasara\Cloudflare\Models\CloudflareZone;
use Nawasara\Cloudflare\Services\CloudflareClient;
use Nawasara\Sync\Jobs\AbstractSyncJob;

/**
 * Full sync semua Cloudflare zones → DB snapshot.
 *
 * Trigger:
 *   - Scheduled: setiap 6 jam (zones jarang berubah)
 *   - Manual: via "Sync Sekarang"
 */
class SyncCloudflareZonesJob extends AbstractSyncJob
{
    public int $timeout = 120;

    protected function service(): string
    {
        return 'cloudflare';
    }

    protected function action(): string
    {
        return 'sync_zones';
    }

    protected function targetType(): ?string
    {
        return 'CloudflareZone';
    }

    protected function targetId(): ?string
    {
        return null;
    }

    protected function execute(): array
    {
        $cf = app(CloudflareClient::class);

        if (! $cf->isConfigured()) {
            throw new \RuntimeException('Cloudflare client is not configured');
        }

        $zones = $cf->getZones(['per_page' => 50, 'page' => 1]);
        $allZones = $zones['result'] ?? [];

        // Pagination: ambil semua page kalau ada
        $totalPages = $zones['result_info']['total_pages'] ?? 1;
        for ($page = 2; $page <= $totalPages; $page++) {
            $more = $cf->getZones(['per_page' => 50, 'page' => $page]);
            $allZones = array_merge($allZones, $more['result'] ?? []);
        }

        $stats = [
            'total' => count($allZones),
            'created' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'deactivated' => 0,
        ];

        $seenZoneIds = [];

        foreach ($allZones as $row) {
            $zoneId = $row['id'] ?? null;
            $name = $row['name'] ?? null;
            if (! $zoneId || ! $name) continue;

            $seenZoneIds[] = $zoneId;

            $attrs = [
                'zone_id' => $zoneId,
                'name' => $name,
                'status' => $row['status'] ?? null,
                'type' => $row['type'] ?? null,
                'plan_name' => $row['plan']['name'] ?? null,
                'name_servers' => $row['name_servers'] ?? null,
                'original_name_servers' => $row['original_name_servers'] ?? null,
                'cf_created_at' => isset($row['created_on']) ? \Carbon\Carbon::parse($row['created_on']) : null,
                'cf_modified_at' => isset($row['modified_on']) ? \Carbon\Carbon::parse($row['modified_on']) : null,
                'sync_status' => CloudflareZone::SYNC_SYNCED,
                'sync_error' => null,
                'last_synced_at' => now(),
            ];

            $existing = CloudflareZone::where('zone_id', $zoneId)->first();

            if ($existing) {
                $tempModel = new CloudflareZone(array_merge($existing->toArray(), $attrs));
                $newHash = $tempModel->computeContentHash();

                if ($existing->content_hash === $newHash && $existing->isSynced()) {
                    $stats['unchanged']++;
                    continue;
                }

                $existing->update(array_merge($attrs, ['content_hash' => $newHash]));
                $stats['updated']++;
            } else {
                $tempModel = new CloudflareZone($attrs);
                $newHash = $tempModel->computeContentHash();
                CloudflareZone::create(array_merge($attrs, ['content_hash' => $newHash]));
                $stats['created']++;
            }
        }

        // Zones yang hilang dari Cloudflare = di-mark deactivated
        if (! empty($seenZoneIds)) {
            $stats['deactivated'] = CloudflareZone::whereNotIn('zone_id', $seenZoneIds)
                ->where('status', '!=', 'deleted')
                ->update([
                    'status' => 'deleted',
                    'sync_status' => CloudflareZone::SYNC_SYNCED,
                    'last_synced_at' => now(),
                ]);
        }

        // Update DNS records count for each zone
        \DB::statement('
            UPDATE nawasara_cloudflare_zones z
            SET dns_records_count = (
                SELECT COUNT(*) FROM nawasara_cloudflare_dns_records r
                WHERE r.zone_id = z.zone_id
            )
        ');

        return $stats;
    }
}
