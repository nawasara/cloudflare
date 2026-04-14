<?php

namespace Nawasara\Cloudflare\Console\Commands;

use Illuminate\Console\Command;
use Nawasara\Cloudflare\Models\EndpointHealth;
use Nawasara\Cloudflare\Services\DnsHealthChecker;
use Nawasara\Registry\Models\Asset;

class HealthCheckCommand extends Command
{
    protected $signature = 'cloudflare:health-check
                            {--ssl : Also probe SSL certificates (slower)}
                            {--chunk=50 : Number of identifiers per parallel batch}
                            {--limit= : Max records to check this run}
                            {--stale=15 : Skip records checked within N minutes (use 0 to force)}';

    protected $description = 'Probe DNS endpoints (subdomain assets from Cloudflare) and store health status';

    public function handle(DnsHealthChecker $checker): int
    {
        $withSsl = (bool) $this->option('ssl');
        $chunk = max(1, (int) $this->option('chunk'));
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $staleMinutes = (int) $this->option('stale');

        $query = Asset::query()
            ->where('package_ref', 'cloudflare')
            ->where('type', 'subdomain')
            ->whereNotNull('identifier');

        if ($staleMinutes > 0) {
            $cutoff = now()->subMinutes($staleMinutes);
            $col = $withSsl ? 'ssl_checked_at' : 'checked_at';
            $freshIds = EndpointHealth::whereNotNull($col)
                ->where($col, '>=', $cutoff)
                ->pluck('identifier');
            if ($freshIds->isNotEmpty()) {
                $query->whereNotIn('identifier', $freshIds);
            }
        }

        if ($limit) {
            $query->limit($limit);
        }

        $identifiers = $query->pluck('identifier')->all();
        $total = count($identifiers);

        if ($total === 0) {
            $this->info('Tidak ada record yang perlu di-check (semua masih segar).');
            return self::SUCCESS;
        }

        $mode = $withSsl ? 'HTTP+SSL' : 'HTTP only';
        $this->info("Checking {$total} endpoints ({$mode}, chunk={$chunk})...");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $stats = ['ok' => 0, 'warning' => 0, 'critical' => 0, 'unknown' => 0];

        foreach (array_chunk($identifiers, $chunk) as $batch) {
            $results = $checker->checkMany($batch, $withSsl, $chunk);
            foreach ($results as $r) {
                $state = $r['state'] ?? 'unknown';
                $stats[$state] = ($stats[$state] ?? 0) + 1;
            }
            $bar->advance(count($batch));
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['ok', 'warning', 'critical', 'unknown'],
            [[$stats['ok'], $stats['warning'], $stats['critical'], $stats['unknown']]]
        );

        return self::SUCCESS;
    }
}
