<?php

namespace Nawasara\Cloudflare\Livewire\Health\Section;

use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Nawasara\Cloudflare\Models\EndpointHealth;
use Nawasara\Cloudflare\Services\DnsHealthChecker;
use Nawasara\Registry\Models\Asset;
use Nawasara\Registry\Models\Opd;
use Nawasara\Ui\Livewire\Concerns\HasBrowserToast;

class DnsHealth extends Component
{
    use HasBrowserToast;
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public $opdFilter = '';

    #[Url(except: '')]
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
    public function opdOptions(): array
    {
        return collect(['all' => 'Semua OPD'])
            ->merge(
                $this->opdList->mapWithKeys(fn ($o) => [(string) $o->id => $o->code . ' - ' . $o->name])
            )
            ->all();
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

        if ($this->stateFilter) {
            $stateIdentifiers = EndpointHealth::where('state', $this->stateFilter)->pluck('identifier');
            if ($this->stateFilter === 'unchecked') {
                // "unchecked" = no row in endpoint_health at all
                $checkedIds = EndpointHealth::pluck('identifier');
                $q->whereNotIn('identifier', $checkedIds);
            } else {
                $q->whereIn('identifier', $stateIdentifiers);
            }
        }

        return $q->orderBy('identifier')->paginate(25);
    }

    #[Computed]
    public function rows()
    {
        $page = $this->items;
        $identifiers = $page->pluck('identifier')->all();
        $healthMap = EndpointHealth::whereIn('identifier', $identifiers)
            ->get()
            ->keyBy('identifier');

        $rows = [];
        foreach ($page as $asset) {
            $h = $healthMap[$asset->identifier] ?? null;
            $rows[] = [
                'asset' => $asset,
                'health' => $h,
                'state' => $h?->state ?? 'unchecked',
            ];
        }

        return $rows;
    }

    #[Computed]
    public function summary()
    {
        $totalAssets = Asset::where('package_ref', 'cloudflare')
            ->where('type', 'subdomain')
            ->count();

        $byState = EndpointHealth::query()
            ->selectRaw('state, COUNT(*) as c')
            ->groupBy('state')
            ->pluck('c', 'state')
            ->toArray();

        $checked = array_sum($byState);

        return [
            'total' => $totalAssets,
            'ok' => $byState['ok'] ?? 0,
            'warning' => $byState['warning'] ?? 0,
            'critical' => $byState['critical'] ?? 0,
            'unknown' => $byState['unknown'] ?? 0,
            'unchecked' => max(0, $totalAssets - $checked),
        ];
    }

    public function checkOne(int $assetId)
    {
        Gate::authorize('cloudflare.health.view');

        $asset = Asset::find($assetId);
        if (! $asset) return;

        app(DnsHealthChecker::class)->checkOne($asset->identifier, true);
        unset($this->rows, $this->summary);
        $this->toastSuccess("Checked {$asset->identifier}");
    }

    public function checkPage()
    {
        Gate::authorize('cloudflare.health.view');

        $ids = $this->items->pluck('identifier')->all();
        if (empty($ids)) {
            $this->toastError('Tidak ada record untuk dicek');
            return;
        }

        $start = microtime(true);
        app(DnsHealthChecker::class)->checkMany($ids, false, 25);
        $elapsed = round(microtime(true) - $start, 1);

        unset($this->rows, $this->summary);
        $this->toastSuccess(count($ids) . " record dicek dalam {$elapsed}s (HTTP only)");
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
