<?php

namespace Nawasara\Cloudflare\Livewire\Analytics\Section;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Nawasara\Cloudflare\Services\CloudflareClient;
use Nawasara\Registry\Models\Asset;
use Nawasara\Registry\Models\Opd;

class OpdRollup extends Component
{
    public $opdId = '';
    public string $period = '-1440';

    protected CloudflareClient $cloudflare;

    public function boot(CloudflareClient $cloudflare)
    {
        $this->cloudflare = $cloudflare;
    }

    #[Computed]
    public function opdList()
    {
        return Opd::query()
            ->whereHas('assets', fn ($q) => $q
                ->where('package_ref', 'cloudflare')
                ->where('type', 'domain'))
            ->orderBy('name')
            ->get(['id', 'name', 'code']);
    }

    #[Computed]
    public function domains()
    {
        if (! $this->opdId) {
            return collect();
        }

        return Asset::query()
            ->where('opd_id', $this->opdId)
            ->where('package_ref', 'cloudflare')
            ->where('type', 'domain')
            ->whereNotNull('external_id')
            ->with('pic:id,name')
            ->get();
    }

    #[Computed]
    public function rollup()
    {
        if (! $this->opdId) {
            return null;
        }

        $zoneIds = $this->domains->pluck('external_id')->filter()->values()->all();
        if (empty($zoneIds)) {
            return [
                'totals' => null,
                'per_zone' => [],
                'error' => 'OPD ini belum punya domain yang tersinkron dari Cloudflare.',
            ];
        }

        return $this->cloudflare->getAggregatedAnalytics($zoneIds, $this->period);
    }

    public function updatedOpdId()
    {
        unset($this->rollup, $this->domains);
    }

    public function updatedPeriod()
    {
        unset($this->rollup);
    }

    protected function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
        if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
        if ($bytes >= 1024) return number_format($bytes / 1024, 2) . ' KB';
        return $bytes . ' B';
    }

    public function render()
    {
        return view('nawasara-cloudflare::livewire.pages.analytics.section.opd-rollup');
    }
}
