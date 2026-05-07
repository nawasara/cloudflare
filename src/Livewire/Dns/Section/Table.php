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
use Nawasara\Ui\Livewire\Concerns\HasExport;

class Table extends Component
{
    use HasBrowserToast;
    use HasExport;
    use WithPagination;

    #[Url]
    public string $zone = '';

    public string $search = '';

    /**
     * Multi-select: list of CF record types to include (A, AAAA, CNAME, ...).
     * Empty array = no filter (show all types). Aligns with filter-panel
     * Alpine multi-select payload.
     */
    public array $typeFilter = [];

    public string $sort = 'newest'; // newest | oldest | modified | name
    public int $perPage = 25;

    // Form modal
    public ?int $editingId = null;
    public string $formType = 'A';
    public string $formName = '';
    public string $formContent = '';
    public int $formTtl = 1;
    public bool $formProxied = true;
    public int $formPriority = 10;
    public string $formComment = '';
    public string $formTagsInput = ''; // comma-separated user input
    public $formOpdId = '';
    public $formPicId = '';

    /**
     * Selected record ids for bulk actions. Toggling individual checkboxes
     * is handled by Alpine in the view (zero round-trips per click); the
     * frontend pushes the latest array via $wire.set('selected', ...) right
     * before invoking bulkDelete(). The selectAll header checkbox is also
     * Alpine-driven, computed from selectedIds vs the current page's ids.
     */
    public array $selected = [];

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
            'type' => ! empty($this->typeFilter) ? $this->typeFilter : null,
            'sort' => $this->sort ?: null,
        ], $this->perPage);
    }

    #[Computed]
    public function lastSyncedAt(): ?string
    {
        $when = $this->repo()->lastSyncedAt();
        return $when ? $when->diffForHumans() : null;
    }

    public function updatedZone(): void { $this->resetPage(); $this->resetSelection(); }
    public function updatedSearch(): void { $this->resetPage(); $this->resetSelection(); }
    public function updatedTypeFilter(): void { $this->resetPage(); $this->resetSelection(); }
    public function updatedSort(): void { $this->resetPage(); $this->resetSelection(); }

    /**
     * Reset selection from server-side flows (filter changes, post-action
     * cleanup). Frontend Alpine state should also call clear() locally on
     * the same triggers - the wire:loading/morph cycle propagates this back
     * via the empty $selected array on next render.
     */
    public function resetSelection(): void
    {
        $this->selected = [];
    }

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
        $this->formComment = (string) ($record->comment ?? '');
        $this->formTagsInput = is_array($record->tags) ? implode(', ', $record->tags) : '';

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
        $this->formComment = '';
        $this->formTagsInput = '';
        $this->formOpdId = '';
        $this->formPicId = '';
    }

    /**
     * Parse comma/newline-separated tags input into a clean array.
     * Cloudflare allows letters, digits, and `_-+./@`; we strip whitespace,
     * dedupe, and drop empties.
     */
    protected function parseTagsInput(string $raw): array
    {
        $parts = preg_split('/[,\n\r]+/', $raw) ?: [];
        $tags = [];
        foreach ($parts as $p) {
            $t = trim($p);
            if ($t !== '') {
                $tags[] = $t;
            }
        }
        return array_values(array_unique($tags));
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
            'comment' => trim($this->formComment) ?: null,
            'tags' => $this->parseTagsInput($this->formTagsInput),
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

    // ─── Bulk Operations ────────────────────────────────

    public function bulkDelete(): void
    {
        Gate::authorize('cloudflare.dns.delete');

        if (empty($this->selected)) {
            $this->toastError('Tidak ada record yang dipilih.');
            return;
        }

        $count = 0;
        foreach ($this->selected as $id) {
            try {
                $this->repo()->delete((int) $id);
                $count++;
            } catch (\Throwable $e) {
                // Skip failures; per-record sync_error reflects status
            }
        }

        $this->toastSuccess("Delete dispatched untuk {$count} record.");
        $this->resetSelection();
    }

    // ─── Export (HasExport trait) ────────────────────────

    /**
     * Base filename for exported file. Includes the active zone for context;
     * the trait appends timestamp + extension. Per spec, the export covers
     * the FULL dataset of the selected zone, not the filtered view.
     */
    protected function exportFilename(): string
    {
        $zoneSlug = $this->zone
            ? str_replace('.', '-', strtolower($this->zone))
            : 'all-zones';
        return "dns-records-{$zoneSlug}";
    }

    /**
     * All DNS records of the active zone, materialised as plain rows for the
     * exporter. Includes Registry-linked OPD/PIC info because that is the most
     * common reason to export (handover docs, audit, OPD reports). Order
     * follows table reading order to match what users see on screen.
     */
    protected function exportData(): iterable
    {
        if (! $this->zone) {
            return [];
        }

        $records = CloudflareDnsRecord::query()
            ->forZone($this->zone)
            ->orderBy('name')
            ->get();

        $assets = Asset::query()
            ->where('package_ref', 'cloudflare')
            ->whereIn('external_id', $records->pluck('record_id')->filter())
            ->with(['opd:id,name,code', 'pic:id,name,position'])
            ->get()
            ->keyBy('external_id');

        return $records->map(function ($r) use ($assets) {
            $asset = $assets[$r->record_id] ?? null;
            return [
                'Type' => $r->type,
                'Name' => $r->name,
                'Content' => $r->content,
                'Proxied' => in_array($r->type, ['A', 'AAAA', 'CNAME']) ? ($r->proxied ? 'Yes' : 'No') : '',
                'TTL' => $r->ttl === 1 ? 'Auto' : $r->ttl,
                'Priority' => $r->priority ?? '',
                'Comment' => (string) ($r->comment ?? ''),
                'Tags' => is_array($r->tags) ? implode(', ', $r->tags) : '',
                'OPD' => $asset?->opd?->name ?? '',
                'OPD Code' => $asset?->opd?->code ?? '',
                'PIC' => $asset?->pic?->name ?? '',
                'PIC Position' => $asset?->pic?->position ?? '',
                'Created (Cloudflare)' => $r->cf_created_at?->format('Y-m-d H:i:s') ?? '',
                'Modified (Cloudflare)' => $r->cf_modified_at?->format('Y-m-d H:i:s') ?? '',
                'Sync Status' => $r->sync_status,
            ];
        });
    }

    public function render()
    {
        return view('nawasara-cloudflare::livewire.pages.dns.section.table');
    }
}
