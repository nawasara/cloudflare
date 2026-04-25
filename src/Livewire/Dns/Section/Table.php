<?php

namespace Nawasara\Cloudflare\Livewire\Dns\Section;

use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Nawasara\Cloudflare\Models\CloudflareDnsRecord;
use Nawasara\Cloudflare\Models\CloudflareZone;
use Nawasara\Cloudflare\Repositories\CloudflareDnsRecordRepository;
use Nawasara\Cloudflare\Services\DnsRegistrySync;
use Nawasara\Registry\Models\Asset;
use Nawasara\Registry\Models\Opd;
use Nawasara\Registry\Models\Pic;
use Nawasara\Ui\Livewire\Concerns\HasBrowserToast;

class Table extends Component
{
    use HasBrowserToast;
    use WithPagination;

    #[Url]
    public string $zone = '';

    public string $search = '';
    public string $typeFilter = '';
    public int $perPage = 25;

    // Form modal
    public ?int $editingId = null;
    public string $formType = 'A';
    public string $formName = '';
    public string $formContent = '';
    public int $formTtl = 1;
    public bool $formProxied = true;
    public int $formPriority = 10;
    public $formOpdId = '';
    public $formPicId = '';

    public function mount(): void
    {
        if (! $this->zone) {
            $first = CloudflareZone::where('status', 'active')->orderBy('name')->first();
            $this->zone = $first?->zone_id ?? '';
        }
    }

    protected function repo(): CloudflareDnsRecordRepository
    {
        return new CloudflareDnsRecordRepository($this->zone ?: null);
    }

    #[Computed]
    public function zoneOptions(): array
    {
        return CloudflareZone::where('status', 'active')
            ->orderBy('name')
            ->pluck('name', 'zone_id')
            ->all();
    }

    #[Computed]
    public function records()
    {
        if (! $this->zone) {
            return CloudflareDnsRecord::query()->whereRaw('0=1')->paginate($this->perPage);
        }

        return $this->repo()->list([
            'search' => $this->search ?: null,
            'type' => $this->typeFilter ?: null,
        ], $this->perPage);
    }

    #[Computed]
    public function lastSyncedAt(): ?string
    {
        $when = $this->repo()->lastSyncedAt();
        return $when ? $when->diffForHumans() : null;
    }

    public function updatedZone(): void { $this->resetPage(); }
    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedTypeFilter(): void { $this->resetPage(); }

    public function refreshRecords(): void
    {
        Gate::authorize('cloudflare.dns.view');

        $this->repo()->syncNow();
        $this->toastSuccess('Sync dispatched. Refresh dalam beberapa detik.');
    }

    #[On('openCreateDns')]
    public function openCreate(): void
    {
        Gate::authorize('cloudflare.dns.create');

        $this->resetForm();
        if ($this->zone) {
            $zoneAsset = Asset::where('package_ref', 'cloudflare')
                ->where('external_id', $this->zone)
                ->first();
            $this->formOpdId = $zoneAsset?->opd_id ?: '';
            $this->formPicId = $zoneAsset?->pic_id ?: '';
        }
        $this->dispatch('modal-open:dns-form');
    }

    public function openEdit(int $id): void
    {
        Gate::authorize('cloudflare.dns.edit');

        $record = CloudflareDnsRecord::find($id);
        if (! $record) return;

        $this->editingId = $id;
        $this->formType = $record->type;
        $this->formName = $record->name;
        $this->formContent = $record->content;
        $this->formTtl = $record->ttl;
        $this->formProxied = $record->proxied;
        $this->formPriority = $record->priority ?? 10;

        $asset = Asset::where('package_ref', 'cloudflare')
            ->where('external_id', $record->record_id)
            ->first();
        $this->formOpdId = $asset?->opd_id ?: '';
        $this->formPicId = $asset?->pic_id ?: '';

        $this->dispatch('modal-open:dns-form');
    }

    public function updatedFormOpdId(): void
    {
        $this->formPicId = '';
    }

    #[Computed]
    public function opdList()
    {
        return Opd::orderBy('name')->get(['id', 'name', 'code']);
    }

    #[Computed]
    public function picList()
    {
        if (! $this->formOpdId) {
            return collect();
        }

        return Pic::where('opd_id', $this->formOpdId)
            ->orderBy('name')
            ->get(['id', 'name', 'position']);
    }

    protected function resetForm(): void
    {
        $this->editingId = null;
        $this->formType = 'A';
        $this->formName = '';
        $this->formContent = '';
        $this->formTtl = 1;
        $this->formProxied = true;
        $this->formPriority = 10;
        $this->formOpdId = '';
        $this->formPicId = '';
    }

    public function save(): void
    {
        Gate::authorize($this->editingId ? 'cloudflare.dns.edit' : 'cloudflare.dns.create');

        $this->validate([
            'formType' => 'required',
            'formName' => 'required',
            'formContent' => 'required',
        ]);

        $data = [
            'type' => $this->formType,
            'name' => $this->formName,
            'content' => $this->formContent,
            'ttl' => $this->formTtl,
            'proxied' => in_array($this->formType, ['A', 'AAAA', 'CNAME']) ? $this->formProxied : false,
        ];

        if ($this->formType === 'MX') {
            $data['priority'] = $this->formPriority;
        }

        try {
            if ($this->editingId) {
                $this->repo()->update($this->editingId, $data);
                $this->toastSuccess('DNS record sedang di-update');
            } else {
                $this->repo()->create($data);
                $this->toastSuccess('DNS record sedang dibuat');
            }
            $this->dispatch('modal-close:dns-form');
        } catch (\Throwable $e) {
            $this->toastError($e->getMessage());
        }
    }

    public function deleteRecord(int $id): void
    {
        Gate::authorize('cloudflare.dns.delete');

        try {
            $this->repo()->delete($id);
            $this->toastSuccess('Delete dispatched');
        } catch (\Throwable $e) {
            $this->toastError($e->getMessage());
        }
    }

    #[Computed]
    public function assetMap()
    {
        $recordIds = $this->records->pluck('record_id')->filter()->all();
        if (empty($recordIds)) {
            return collect();
        }

        return Asset::where('package_ref', 'cloudflare')
            ->whereIn('external_id', $recordIds)
            ->with(['opd:id,name,code', 'pic:id,name'])
            ->get()
            ->keyBy('external_id');
    }

    public function syncRegistry(DnsRegistrySync $sync): void
    {
        Gate::authorize('cloudflare.dns.view');

        if (! $this->zone) {
            $this->toastError('Pilih zone terlebih dahulu');
            return;
        }

        $stats = $sync->syncZone($this->zone);
        unset($this->assetMap);

        $parts = [];
        if ($stats['created']) $parts[] = "{$stats['created']} baru";
        if ($stats['linked']) $parts[] = "{$stats['linked']} terhubung";
        if ($stats['updated']) $parts[] = "{$stats['updated']} diperbarui";
        if (! $parts) $parts[] = 'semua up-to-date';

        $this->toastSuccess('Sync DNS: '.implode(', ', $parts)." (dari {$stats['total']} record)");
    }

    public function render()
    {
        return view('nawasara-cloudflare::livewire.pages.dns.section.table');
    }
}
