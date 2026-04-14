<?php

namespace Nawasara\Cloudflare\Livewire\Dns\Section;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Nawasara\Cloudflare\Services\CloudflareClient;
use Nawasara\Cloudflare\Services\DnsRegistrySync;
use Nawasara\Registry\Models\Asset;
use Nawasara\Registry\Models\Opd;
use Nawasara\Registry\Models\Pic;

class Table extends Component
{
    #[Url]
    public string $zone = '';

    public string $search = '';
    public string $typeFilter = '';
    public int $page = 1;

    // Form modal
    public bool $showForm = false;
    public ?string $editingId = null;
    public string $formType = 'A';
    public string $formName = '';
    public string $formContent = '';
    public int $formTtl = 1;
    public bool $formProxied = true;
    public int $formPriority = 10;
    public $formOpdId = '';
    public $formPicId = '';

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
    public function records()
    {
        if (! $this->zone) {
            return ['result' => [], 'result_info' => []];
        }

        $params = [
            'page' => $this->page,
            'per_page' => config('nawasara-cloudflare.per_page', 25),
        ];

        if ($this->search) {
            $params['name.contains'] = $this->search;
        }

        if ($this->typeFilter) {
            $params['type'] = $this->typeFilter;
        }

        return $this->cloudflare->getDnsRecords($this->zone, $params);
    }

    public function updatedZone()
    {
        $this->page = 1;
        unset($this->records);
    }

    public function updatedSearch()
    {
        $this->page = 1;
        unset($this->records);
    }

    public function updatedTypeFilter()
    {
        $this->page = 1;
        unset($this->records);
    }

    public function previousPage()
    {
        $this->page = max(1, $this->page - 1);
        unset($this->records);
    }

    public function nextPage()
    {
        $this->page++;
        unset($this->records);
    }

    #[On('openCreateDns')]
    public function openCreate()
    {
        $this->resetForm();
        // Default OPD/PIC dari zone asset (inherited).
        if ($this->zone) {
            $zoneAsset = Asset::where('package_ref', 'cloudflare')
                ->where('external_id', $this->zone)
                ->first();
            $this->formOpdId = $zoneAsset?->opd_id ?: '';
            $this->formPicId = $zoneAsset?->pic_id ?: '';
        }
        $this->showForm = true;
    }

    public function openEdit(string $recordId)
    {
        $records = $this->records['result'] ?? [];
        $record = collect($records)->firstWhere('id', $recordId);

        if (! $record) {
            return;
        }

        $this->editingId = $recordId;
        $this->formType = $record['type'];
        $this->formName = $record['name'];
        $this->formContent = $record['content'];
        $this->formTtl = $record['ttl'] ?? 1;
        $this->formProxied = $record['proxied'] ?? false;
        $this->formPriority = $record['priority'] ?? 10;

        // Pre-fill OPD/PIC dari asset yang tersinkron (kalau ada).
        $asset = Asset::where('package_ref', 'cloudflare')
            ->where('external_id', $recordId)
            ->first();
        $this->formOpdId = $asset?->opd_id ?: '';
        $this->formPicId = $asset?->pic_id ?: '';

        $this->showForm = true;
    }

    public function updatedFormOpdId()
    {
        // Reset PIC when OPD changes to avoid cross-OPD leakage.
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

    public function save()
    {
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

        if ($this->editingId) {
            $result = $this->cloudflare->updateDnsRecord($this->zone, $this->editingId, $data);
            $message = $result ? 'DNS record berhasil diupdate' : 'Gagal mengupdate DNS record';
        } else {
            $result = $this->cloudflare->createDnsRecord($this->zone, $data);
            $message = $result ? 'DNS record berhasil dibuat' : 'Gagal membuat DNS record';
        }

        if ($result) {
            $asset = app(DnsRegistrySync::class)->linkRecord($this->zone, $result);

            // Override OPD/PIC dari form kalau user set manual.
            if ($asset && ($this->formOpdId !== '' || $this->formPicId !== '')) {
                $asset->update([
                    'opd_id' => $this->formOpdId ?: null,
                    'pic_id' => $this->formPicId ?: null,
                ]);
            }

            toaster_success($message);
            $this->showForm = false;
            unset($this->records, $this->assetMap);
        } else {
            toaster_error($message);
        }
    }

    public function deleteRecord(string $recordId)
    {
        if ($this->cloudflare->deleteDnsRecord($this->zone, $recordId)) {
            app(DnsRegistrySync::class)->unlinkRecord($recordId);
            toaster_success('DNS record berhasil dihapus');
            unset($this->records, $this->assetMap);
        } else {
            toaster_error('Gagal menghapus DNS record');
        }
    }

    #[Computed]
    public function assetMap()
    {
        $records = $this->records['result'] ?? [];
        if (empty($records)) {
            return collect();
        }

        $ids = collect($records)->pluck('id')->filter()->all();

        return Asset::where('package_ref', 'cloudflare')
            ->whereIn('external_id', $ids)
            ->with(['opd:id,name,code', 'pic:id,name'])
            ->get()
            ->keyBy('external_id');
    }

    public function syncRegistry(DnsRegistrySync $sync)
    {
        if (! $this->zone) {
            toaster_error('Pilih zone terlebih dahulu');
            return;
        }

        $stats = $sync->syncZone($this->zone);
        unset($this->assetMap);

        $parts = [];
        if ($stats['created']) $parts[] = "{$stats['created']} baru";
        if ($stats['linked']) $parts[] = "{$stats['linked']} terhubung";
        if ($stats['updated']) $parts[] = "{$stats['updated']} diperbarui";
        if (! $parts) $parts[] = 'semua up-to-date';

        toaster_success('Sync DNS: ' . implode(', ', $parts) . " (dari {$stats['total']} record)");
    }

    public function render()
    {
        return view('nawasara-cloudflare::livewire.pages.dns.section.table');
    }
}
