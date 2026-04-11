<?php

namespace Nawasara\Cloudflare\Livewire\Analytics\Section;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Nawasara\Cloudflare\Services\CloudflareClient;

class Overview extends Component
{
    public string $zone = '';
    public string $period = '-1440'; // 24 hours in minutes

    protected CloudflareClient $cloudflare;

    public function boot(CloudflareClient $cloudflare)
    {
        $this->cloudflare = $cloudflare;
    }

    #[Computed]
    public function zones()
    {
        return $this->cloudflare->getCachedZones();
    }

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
