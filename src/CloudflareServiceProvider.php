<?php

namespace Nawasara\Cloudflare;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Nawasara\Cloudflare\Console\Commands\HealthCheckCommand;
use Nawasara\Cloudflare\Console\Commands\SyncRegistryCommand;
use Nawasara\Cloudflare\Jobs\CheckSslExpiryJob;
use Nawasara\Cloudflare\Jobs\DetectAttackJob;
use Nawasara\Cloudflare\Jobs\SyncCloudflareDnsRecordsJob;
use Nawasara\Cloudflare\Jobs\SyncCloudflareZonesJob;
use Nawasara\Cloudflare\Models\AttackWindow;
use Nawasara\Cloudflare\Services\CloudflareClient;
use Nawasara\Cloudflare\Services\DnsHealthChecker;
use Nawasara\Cloudflare\Services\DnsRegistrySync;
use Nawasara\Cloudflare\Services\ZoneRegistrySync;
use Symfony\Component\Finder\Finder;

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
                    SyncCloudflareZonesJob::dispatch(triggerSource: 'scheduled');
                    SyncCloudflareDnsRecordsJob::dispatch(triggerSource: 'scheduled');
                })
                    ->name('cloudflare:sync-api')
                    ->everyThirtyMinutes()
                    ->withoutOverlapping(25);

                // Detect new/changed/deleted CF records and surface them in the registry.
                $schedule->call(function () {
                    $cf = $this->app->make(CloudflareClient::class);
                    $this->app->make(ZoneRegistrySync::class)->sync();
                    $dnsSync = $this->app->make(DnsRegistrySync::class);
                    foreach ($cf->getCachedZones() as $zone) {
                        $dnsSync->syncZone($zone['id']);
                    }
                })
                    ->name('cloudflare:sync-registry')
                    ->everyThirtyMinutes()
                    ->withoutOverlapping(25);

                $schedule->call(function () {
                    $this->app->make(DnsHealthChecker::class)
                        ->runHealthCheck(withSsl: false, chunk: 50, limit: null, staleMinutes: 10);
                })
                    ->name('cloudflare:health-check')
                    ->everyFifteenMinutes()
                    ->withoutOverlapping(20);

                $schedule->call(function () {
                    $this->app->make(DnsHealthChecker::class)
                        ->runHealthCheck(withSsl: true, chunk: 50, limit: null, staleMinutes: 1380);
                })
                    ->name('cloudflare:health-check-ssl')
                    ->dailyAt('02:00')
                    ->withoutOverlapping(60);

                // Membaca hasil pemeriksaan di atas dan memberitakannya.
                //
                // Dijadwalkan SETELAH pemeriksaannya (02:00), bukan bersamaan:
                // membaca angka yang belum diperbarui berarti memberitakan
                // keadaan kemarin, dan pada hari sertifikat benar-benar habis
                // itu selisih yang menentukan.
                //
                // ->timezone() wajib — app.timezone UTC, tanpa itu jadwal
                // jam-dinding meleset tujuh jam.
                // Deteksi serangan yang SEDANG berlangsung.
                //
                // Tiap 2 menit — inilah yang menjawab "apakah sekarang ada
                // situs yang dihantam". Sisa jadwal di paket ini bersifat
                // laporan; yang satu ini bersifat panggilan.
                $schedule->call(fn () => DetectAttackJob::dispatch())
                    ->name('cloudflare:detect-attack')
                    ->everyTwoMinutes()
                    ->withoutOverlapping(5);

                // Membuang cuplikan lama. Ia hanya pembanding, dan tanpa
                // pembersihan ia tumbuh selamanya — kesalahan yang sudah
                // membuat satu tabel di sistem ini mencapai 458 MB.
                $schedule->call(function () {
                    AttackWindow::where(
                        'window_start', '<',
                        now()->subDays((int) config('nawasara-cloudflare.attack_detection.retention_days', 30)),
                    )->delete();
                })
                    ->name('cloudflare:prune-attack-windows')
                    ->dailyAt('04:00')
                    ->withoutOverlapping(30);

                $schedule->call(fn () => CheckSslExpiryJob::dispatch())
                    ->name('cloudflare:check-ssl-expiry')
                    ->dailyAt('03:00')
                    ->timezone(config('nawasara-cloudflare.ssl_alerts.timezone', 'Asia/Jakarta'))
                    ->withoutOverlapping(30);
            });
        }
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/nawasara-cloudflare.php', 'nawasara-cloudflare');

        $this->app->singleton(CloudflareClient::class, fn () => new CloudflareClient);
    }

    public function registerLivewire(): void
    {
        $namespace = 'Nawasara\\Cloudflare\\Livewire';
        $basePath = __DIR__.'/Livewire';

        if (! is_dir($basePath)) {
            return;
        }

        $finder = new Finder;
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
