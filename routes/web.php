<?php

use Illuminate\Support\Facades\Route;
use Nawasara\Cloudflare\Livewire\Zone\Index as ZoneIndex;
use Nawasara\Cloudflare\Livewire\Dns\Index as DnsIndex;
use Nawasara\Cloudflare\Livewire\Firewall\Index as FirewallIndex;
use Nawasara\Cloudflare\Livewire\Analytics\Index as AnalyticsIndex;
use Nawasara\Cloudflare\Livewire\Health\Index as HealthIndex;
use Nawasara\Cloudflare\Livewire\Audit\Index as AuditIndex;
use Nawasara\Cloudflare\Livewire\PageRule\Index as PageRuleIndex;
use Spatie\Permission\Middleware\PermissionMiddleware;

Route::middleware(['web', 'auth'])->prefix('nawasara-cloudflare')->group(function () {
    Route::get('zones', ZoneIndex::class)
        ->middleware(PermissionMiddleware::using('cloudflare.zone.view'))
        ->name('nawasara-cloudflare.zone.index');

    Route::get('dns', DnsIndex::class)
        ->middleware(PermissionMiddleware::using('cloudflare.dns.view'))
        ->name('nawasara-cloudflare.dns.index');

    Route::get('firewall', FirewallIndex::class)
        ->middleware(PermissionMiddleware::using('cloudflare.waf.view'))
        ->name('nawasara-cloudflare.firewall.index');

    Route::get('page-rules', PageRuleIndex::class)
        ->middleware(PermissionMiddleware::using('cloudflare.pagerule.view'))
        ->name('nawasara-cloudflare.page-rule.index');

    Route::get('analytics', AnalyticsIndex::class)
        ->middleware(PermissionMiddleware::using('cloudflare.analytics.view'))
        ->name('nawasara-cloudflare.analytics.index');

    Route::get('health', HealthIndex::class)
        ->middleware(PermissionMiddleware::using('cloudflare.health.view'))
        ->name('nawasara-cloudflare.health.index');

    Route::get('audit', AuditIndex::class)
        ->middleware(PermissionMiddleware::using('cloudflare.audit.view'))
        ->name('nawasara-cloudflare.audit.index');
});
