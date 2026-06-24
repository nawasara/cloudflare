<?php

namespace Nawasara\Cloudflare\Console\Commands;

use Illuminate\Console\Command;
use Nawasara\Cloudflare\Services\DnsHealthChecker;

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

        // Run logic lives in the service so the scheduler can call it without
        // the Artisan command registry (see DnsHealthChecker::runHealthCheck).
        $stats = $checker->runHealthCheck($withSsl, $chunk, $limit, $staleMinutes);

        if ($stats['total'] === 0) {
            $this->info('Tidak ada record yang perlu di-check (semua masih segar).');
            return self::SUCCESS;
        }

        $mode = $withSsl ? 'HTTP+SSL' : 'HTTP only';
        $this->info("Checked {$stats['total']} endpoints ({$mode}, chunk={$chunk}).");

        $this->table(
            ['ok', 'warning', 'critical', 'unknown'],
            [[$stats['ok'], $stats['warning'], $stats['critical'], $stats['unknown']]]
        );

        return self::SUCCESS;
    }
}
