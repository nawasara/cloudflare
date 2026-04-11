<?php

namespace Nawasara\Cloudflare\Livewire\Dns\Section;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Nawasara\Cloudflare\Services\CloudflareClient;

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
            $params['name'] = $this->search;
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
        $this->editingId = null;
        $this->formType = 'A';
        $this->formName = '';
        $this->formContent = '';
        $this->formTtl = 1;
        $this->formProxied = true;
        $this->formPriority = 10;
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
        $this->showForm = true;
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
            toaster_success($message);
            $this->showForm = false;
            unset($this->records);
        } else {
            toaster_error($message);
        }
    }

    public function deleteRecord(string $recordId)
    {
        if ($this->cloudflare->deleteDnsRecord($this->zone, $recordId)) {
            toaster_success('DNS record berhasil dihapus');
            unset($this->records);
        } else {
            toaster_error('Gagal menghapus DNS record');
        }
    }

    public function render()
    {
        return view('nawasara-cloudflare::livewire.pages.dns.section.table');
    }
}
