<?php

namespace Nawasara\Cloudflare\Livewire\Zone;

use Livewire\Attributes\Computed;
use Livewire\Component;
use Nawasara\Cloudflare\Repositories\CloudflareZoneRepository;

class Index extends Component
{
    /**
     * Hero stats untuk Zones page — derive di Index level supaya tidak ter-bias
     * filter table di section/table.blade.php.
     */
    #[Computed]
    public function stats(): array
    {
        $s = (new CloudflareZoneRepository())->stats();

        // SSL coverage % — relevan ke total zone, bukan active (zone non-active
        // tetap punya konfigurasi SSL).
        $sslPct = $s['total'] > 0
            ? round(($s['ssl_strict'] / $s['total']) * 100)
            : 0;

        return [
            ['label' => 'Total Zones', 'value' => number_format($s['total']), 'icon' => 'lucide-globe', 'color' => 'primary'],
            ['label' => 'Active', 'value' => number_format($s['active']), 'icon' => 'lucide-circle-check', 'color' => 'success'],
            ['label' => 'SSL Strict / Full', 'value' => number_format($s['ssl_strict']), 'icon' => 'lucide-shield-check', 'color' => 'info', 'description' => $sslPct.'% dari total'],
            ['label' => 'DNS Records', 'value' => number_format($s['dns_records']), 'icon' => 'lucide-list', 'color' => 'neutral', 'description' => 'di semua zone'],
        ];
    }

    public function render()
    {
        return view('nawasara-cloudflare::livewire.pages.zone.index')
            ->layout('nawasara-ui::components.layouts.app');
    }
}
