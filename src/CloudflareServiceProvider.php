<?php

namespace Nawasara\Cloudflare;

use Livewire\Livewire;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;
use Illuminate\Support\ServiceProvider;
use Nawasara\Cloudflare\Console\Commands\HealthCheckCommand;
use Nawasara\Cloudflare\Console\Commands\SyncRegistryCommand;
use Nawasara\Cloudflare\Services\CloudflareClient;

class CloudflareServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'nawasara-cloudflare');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->registerLivewire();

        if ($this->app->runningInConsole()) {
            $this->commands([
                HealthCheckCommand::class,
                SyncRegistryCommand::class,
            ]);

            $this->app->booted(function () {
                $schedule = $this->app->make(Schedule::class);

                // Dispatch via $schedule->call(), NOT $schedule->command().
                // Package console commands do not reliably surface in the
                // Artisan kernel when the scheduler process boots, so
                // `$schedule->command('cloudflare:sync-registry')` silently
                // died every run ("no commands defined in the cloudflare
                // namespace") and the registry hadn't synced in a week. The
                // run logic lives in the services/jobs, so dispatch directly.

                // Primary sync: pull zones + DNS records from the Cloudflare API
                // into the cloudflare_* tables. This is what drives the "Last
                // sync" shown on the Zones/DNS pages (lastSyncedAt reads the
                // sync_zones / sync_dns_records SyncJob rows). It was previously
                // NOT scheduled at all — only run on the UI's manual refresh —
                // so "Last sync" sat a week stale. Dispatch zones first, then
                // DNS (the DNS job reads the zones table).
                $schedule->call(function () {
                    \Nawasara\Cloudflare\Jobs\SyncCloudflareZonesJob::dispatch(triggerSource: 'scheduled');
                    \Nawasara\Cloudflare\Jobs\SyncCloudflareDnsRecordsJob::dispatch(triggerSource: 'scheduled');
                })
                    ->name('cloudflare:sync-api')
                    ->everyThirtyMinutes()
                    ->withoutOverlapping(25);

                // Detect new/changed/deleted CF records and surface them in the registry.
                $schedule->call(function () {
                    $cf = $this->app->make(CloudflareClient::class);
                    $this->app->make(\Nawasara\Cloudflare\Services\ZoneRegistrySync::class)->sync();
                    $dnsSync = $this->app->make(\Nawasara\Cloudflare\Services\DnsRegistrySync::class);
                    foreach ($cf->getCachedZones() as $zone) {
                        $dnsSync->syncZone($zone['id']);
                    }
                })
                    ->name('cloudflare:sync-registry')
                    ->everyThirtyMinutes()
                    ->withoutOverlapping(25);

                $schedule->call(function () {
                    $this->app->make(\Nawasara\Cloudflare\Services\DnsHealthChecker::class)
                        ->runHealthCheck(withSsl: false, chunk: 50, limit: null, staleMinutes: 10);
                })
                    ->name('cloudflare:health-check')
                    ->everyFifteenMinutes()
                    ->withoutOverlapping(20);

                $schedule->call(function () {
                    $this->app->make(\Nawasara\Cloudflare\Services\DnsHealthChecker::class)
                        ->runHealthCheck(withSsl: true, chunk: 50, limit: null, staleMinutes: 1380);
                })
                    ->name('cloudflare:health-check-ssl')
                    ->dailyAt('02:00')
                    ->withoutOverlapping(60);
            });
        }
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/nawasara-cloudflare.php', 'nawasara-cloudflare');

        $this->app->singleton(CloudflareClient::class, fn () => new CloudflareClient());
    }

    public function registerLivewire(): void
    {
        $namespace = 'Nawasara\\Cloudflare\\Livewire';
        $basePath = __DIR__.'/Livewire';

        if (! is_dir($basePath)) {
            return;
        }

        $finder = new Finder();
        $finder->files()->in($basePath)->name('*.php');

        foreach ($finder as $file) {
            $relativePath = str_replace('/', '\\', $file->getRelativePathname());
            $class = $namespace.'\\'.Str::beforeLast($relativePath, '.php');

            if (class_exists($class)) {
                $alias = 'nawasara-cloudflare.'.
                    Str::of($relativePath)
                        ->replace('.php', '')
                        ->replace('\\', '.')
                        ->replace('/', '.')
                        ->explode('.')
                        ->map(fn ($segment) => Str::kebab($segment))
                        ->join('.');

                Livewire::component($alias, $class);
            }
        }
    }
}
