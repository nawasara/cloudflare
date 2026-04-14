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

                // Detect new/changed/deleted CF records and surface them in the registry.
                $schedule->command('cloudflare:sync-registry')
                    ->everyThirtyMinutes()
                    ->withoutOverlapping(25)
                    ->runInBackground();

                $schedule->command('cloudflare:health-check --stale=10')
                    ->everyFifteenMinutes()
                    ->withoutOverlapping(20)
                    ->runInBackground();

                $schedule->command('cloudflare:health-check --ssl --stale=1380')
                    ->dailyAt('02:00')
                    ->withoutOverlapping(60)
                    ->runInBackground();
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
