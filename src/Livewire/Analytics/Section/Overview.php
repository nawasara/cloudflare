<?php

namespace Nawasara\Cloudflare\Livewire\Analytics\Section;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Nawasara\Cloudflare\Services\CloudflareClient;

class Overview extends Component
{
    #[Url(except: '')]
    public string $zone = '';

    #[Url]
    public string $period = '-1440'; // 24 hours in minutes

    protected CloudflareClient $cloudflare;

    public function boot(CloudflareClient $cloudflare)
    {
        $this->cloudflare = $cloudflare;
    }

    public function mount(): void
    {
        if (! $this->zone) {
            $zones = $this->cloudflare->getCachedZones();
            $this->zone = $zones[0]['id'] ?? '';
        }
    }

    #[Computed]
    public function zones()
    {
        return $this->cloudflare->getCachedZones();
    }

    #[Computed]
    public function zoneOptions(): array
    {
        return collect($this->zones)
            ->mapWithKeys(fn ($z) => [$z['id'] => $z['name']])
            ->all();
    }

    public const PERIOD_OPTIONS = [
        '-1440' => '24 Jam',
        '-4320' => '3 Hari',
        '-10080' => '7 Hari',
        '-43200' => '30 Hari',
    ];

    #[Computed]
    public function analytics()
    {
        if (! $this->zone) {
            return null;
        }

        return $this->cloudflare->getAnalytics($this->zone, $this->period);
    }

    public function updatedZone()
    {
        unset($this->analytics);
    }

    public function updatedPeriod()
    {
        unset($this->analytics);
    }

    protected function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        }
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' B';
    }

    public function render()
    {
        return view('nawasara-cloudflare::livewire.pages.analytics.section.overview');
    }
}
