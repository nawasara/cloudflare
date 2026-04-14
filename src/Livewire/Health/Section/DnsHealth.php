<?php

namespace Nawasara\Cloudflare\Livewire\Health\Section;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Nawasara\Cloudflare\Services\DnsHealthChecker;
use Nawasara\Registry\Models\Asset;
use Nawasara\Registry\Models\Opd;

class DnsHealth extends Component
{
    use WithPagination;

    public string $search = '';
    public $opdFilter = '';
    public string $stateFilter = '';

    public function updatedSearch() { $this->resetPage(); }
    public function updatedOpdFilter() { $this->resetPage(); }
    public function updatedStateFilter() { $this->resetPage(); }

    #[Computed]
    public function opdList()
    {
        return Opd::query()
            ->whereHas('assets', fn ($q) => $q
                ->where('package_ref', 'cloudflare')
                ->where('type', 'subdomain'))
            ->orderBy('name')
            ->get(['id', 'name', 'code']);
    }

    #[Computed]
    public function items()
    {
        $q = Asset::query()
            ->where('package_ref', 'cloudflare')
            ->where('type', 'subdomain')
            ->with(['opd:id,name,code', 'pic:id,name']);

        if ($this->search) {
            $q->where('identifier', 'like', '%' . $this->search . '%');
        }
        if ($this->opdFilter) {
            $q->where('opd_id', $this->opdFilter);
        }

        return $q->orderBy('identifier')->paginate(25);
    }

    /**
     * Merge paginated assets with cached health data, then apply state filter.
     */
    #[Computed]
    public function rows()
    {
        $page = $this->items;
        $identifiers = $page->pluck('identifier')->all();
        $healthMap = app(DnsHealthChecker::class)->getCachedMany($identifiers);

        $rows = [];
        foreach ($page as $asset) {
            $health = $healthMap[$asset->identifier] ?? null;
            $state = $health ? DnsHealthChecker::overallState($health) : 'unchecked';

            if ($this->stateFilter && $state !== $this->stateFilter) {
                continue;
            }

            $rows[] = [
                'asset' => $asset,
                'health' => $health,
                'state' => $state,
            ];
        }

        return $rows;
    }

    #[Computed]
    public function summary()
    {
        // Summary computed against ALL assets (not paginated), from cache only.
        $counts = ['ok' => 0, 'warning' => 0, 'critical' => 0, 'unknown' => 0, 'unchecked' => 0, 'total' => 0];

        $all = Asset::query()
            ->where('package_ref', 'cloudflare')
            ->where('type', 'subdomain')
            ->pluck('identifier')
            ->all();

        $counts['total'] = count($all);
        $healthMap = app(DnsHealthChecker::class)->getCachedMany($all);

        foreach ($all as $id) {
            $health = $healthMap[$id] ?? null;
            $state = $health ? DnsHealthChecker::overallState($health) : 'unchecked';
            $counts[$state] = ($counts[$state] ?? 0) + 1;
        }

        return $counts;
    }

    public function checkOne(int $assetId)
    {
        $asset = Asset::find($assetId);
        if (! $asset) return;

        app(DnsHealthChecker::class)->checkOne($asset->identifier);
        unset($this->rows, $this->summary);
        toaster_success("Checked {$asset->identifier}");
    }

    public function checkPage()
    {
        $ids = $this->items->pluck('identifier')->all();
        if (empty($ids)) {
            toaster_error('Tidak ada record untuk dicek');
            return;
        }

        $start = microtime(true);
        app(DnsHealthChecker::class)->checkManyHttp($ids, 15);
        $elapsed = round(microtime(true) - $start, 1);

        unset($this->rows, $this->summary);
        toaster_success(count($ids) . " record dicek dalam {$elapsed}s (HTTP only, klik Check per record untuk SSL)");
    }

    public function setStateFilter(string $state)
    {
        $this->stateFilter = $this->stateFilter === $state ? '' : $state;
        $this->resetPage();
    }

    public function render()
    {
        return view('nawasara-cloudflare::livewire.pages.health.section.dns-health');
    }
}
