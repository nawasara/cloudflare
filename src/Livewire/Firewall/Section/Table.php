<?php

namespace Nawasara\Cloudflare\Livewire\Firewall\Section;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Nawasara\Cloudflare\Services\CloudflareClient;

class Table extends Component
{
    #[Url(except: '')]
    public string $zone = '';

    // Form modal
    public bool $showForm = false;
    public ?string $editingId = null;
    public string $formDescription = '';
    public string $formExpression = '';
    public string $formAction = 'block';
    public bool $formPaused = false;

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

    #[Computed]
    public function rules()
    {
        if (! $this->zone) {
            return [];
        }

        return $this->cloudflare->getFirewallRules($this->zone);
    }

    public function updatedZone()
    {
        unset($this->rules);
    }

    #[On('openCreateFirewall')]
    public function openCreate()
    {
        $this->editingId = null;
        $this->formDescription = '';
        $this->formExpression = '';
        $this->formAction = 'block';
        $this->formPaused = false;
        $this->showForm = true;
    }

    public function openEdit(string $ruleId)
    {
        $rule = collect($this->rules)->firstWhere('id', $ruleId);

        if (! $rule) {
            return;
        }

        $this->editingId = $ruleId;
        $this->formDescription = $rule['description'] ?? '';
        $this->formExpression = $rule['filter']['expression'] ?? '';
        $this->formAction = $rule['action'] ?? 'block';
        $this->formPaused = $rule['paused'] ?? false;
        $this->showForm = true;
    }

    public function save()
    {
        $this->validate([
            'formExpression' => 'required',
            'formAction' => 'required',
        ]);

        $data = [
            'description' => $this->formDescription,
            'action' => $this->formAction,
            'paused' => $this->formPaused,
            'filter' => [
                'expression' => $this->formExpression,
            ],
        ];

        if ($this->editingId) {
            $rule = collect($this->rules)->firstWhere('id', $this->editingId);
            $data['filter']['id'] = $rule['filter']['id'] ?? '';
            $result = $this->cloudflare->updateFirewallRule($this->zone, $this->editingId, $data);
            $message = $result ? 'Firewall rule berhasil diupdate' : 'Gagal mengupdate firewall rule';
        } else {
            $result = $this->cloudflare->createFirewallRule($this->zone, $data);
            $message = $result ? 'Firewall rule berhasil dibuat' : 'Gagal membuat firewall rule';
        }

        if ($result) {
            toaster_success($message);
            $this->showForm = false;
            unset($this->rules);
        } else {
            toaster_error($message);
        }
    }

    public function deleteRule(string $ruleId)
    {
        if ($this->cloudflare->deleteFirewallRule($this->zone, $ruleId)) {
            toaster_success('Firewall rule berhasil dihapus');
            unset($this->rules);
        } else {
            toaster_error('Gagal menghapus firewall rule');
        }
    }

    public function render()
    {
        return view('nawasara-cloudflare::livewire.pages.firewall.section.table');
    }
}
