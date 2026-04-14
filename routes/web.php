<?php

use Illuminate\Support\Facades\Route;
use Nawasara\Cloudflare\Livewire\Zone\Index as ZoneIndex;
use Nawasara\Cloudflare\Livewire\Dns\Index as DnsIndex;
use Nawasara\Cloudflare\Livewire\Firewall\Index as FirewallIndex;
use Nawasara\Cloudflare\Livewire\Analytics\Index as AnalyticsIndex;
use Nawasara\Cloudflare\Livewire\Health\Index as HealthIndex;

Route::middleware(['web', 'auth'])->prefix('nawasara-cloudflare')->group(function () {
    Route::get('zones', ZoneIndex::class)->name('nawasara-cloudflare.zone.index');
    Route::get('dns', DnsIndex::class)->name('nawasara-cloudflare.dns.index');
    Route::get('firewall', FirewallIndex::class)->name('nawasara-cloudflare.firewall.index');
    Route::get('analytics', AnalyticsIndex::class)->name('nawasara-cloudflare.analytics.index');
    Route::get('health', HealthIndex::class)->name('nawasara-cloudflare.health.index');
});
