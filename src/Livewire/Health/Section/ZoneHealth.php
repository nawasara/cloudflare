<?php

namespace Nawasara\Cloudflare\Livewire\Health\Section;

use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\Attributes\Computed;
use Nawasara\Cloudflare\Services\CloudflareClient;
use Nawasara\Registry\Models\Asset;

class ZoneHealth extends Component
{
    public string $stateFilter = '';

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
    public function healthData()
    {
        $zones = $this->zones;
        $rows = [];

        $assets = Asset::where('package_ref', 'cloudflare')
            ->where('type', 'domain')
            ->whereNotNull('external_id')
            ->with(['opd:id,name,code', 'pic:id,name'])
            ->get()
            ->keyBy('external_id');

        foreach ($zones as $zone) {
            $health = $this->cloudflare->getZoneHealth($zone);
            $asset = $assets[$zone['id']] ?? null;
            $rows[] = [
                'zone' => $zone,
                'health' => $health,
                'opd' => $asset?->opd,
                'pic' => $asset?->pic,
            ];
        }

        return $rows;
    }

    #[Computed]
    public function summary()
    {
        $counts = ['ok' => 0, 'warning' => 0, 'critical' => 0, 'unknown' => 0];
        foreach ($this->healthData as $row) {
            $state = $row['health']['overall'] ?? 'unknown';
            $counts[$state] = ($counts[$state] ?? 0) + 1;
        }
        $counts['total'] = count($this->healthData);

        return $counts;
    }

    #[Computed]
    public function filteredRows()
    {
        if (! $this->stateFilter) {
            return $this->healthData;
        }

        return array_values(array_filter(
            $this->healthData,
            fn ($r) => ($r['health']['overall'] ?? null) === $this->stateFilter
        ));
    }

    public function refreshAll()
    {
        Gate::authorize('cloudflare.health.view');

        foreach ($this->zones as $zone) {
            $this->cloudflare->forgetZoneHealth($zone['id']);
        }
        unset($this->healthData, $this->summary, $this->filteredRows);
        toaster_success('Zone health di-refresh');
    }

    public function setFilter(string $state)
    {
        $this->stateFilter = $this->stateFilter === $state ? '' : $state;
        unset($this->filteredRows);
    }

    public function render()
    {
        return view('nawasara-cloudflare::livewire.pages.health.section.zone-health');
    }
}
