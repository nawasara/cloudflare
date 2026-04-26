<?php

namespace Nawasara\Cloudflare\Livewire\Firewall\Section;

use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Nawasara\Cloudflare\Services\CloudflareClient;
use Nawasara\Ui\Livewire\Concerns\HasBrowserToast;

class Table extends Component
{
    use HasBrowserToast;

    #[Url(except: '')]
    public string $zone = '';

    // Form modal
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
        Gate::authorize('cloudflare.waf.create');

        $this->editingId = null;
        $this->formDescription = '';
        $this->formExpression = '';
        $this->formAction = 'block';
        $this->formPaused = false;
        $this->dispatch('modal-open:firewall-form');
    }

    public function openEdit(string $ruleId)
    {
        Gate::authorize('cloudflare.waf.edit');

        $rule = collect($this->rules)->firstWhere('id', $ruleId);

        if (! $rule) {
            return;
        }

        $this->editingId = $ruleId;
        $this->formDescription = $rule['description'] ?? '';
        $this->formExpression = $rule['filter']['expression'] ?? '';
        $this->formAction = $rule['action'] ?? 'block';
        $this->formPaused = $rule['paused'] ?? false;
        $this->dispatch('modal-open:firewall-form');
    }

    public function save()
    {
        Gate::authorize($this->editingId ? 'cloudflare.waf.edit' : 'cloudflare.waf.create');

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
            $this->toastSuccess($message);
            $this->dispatch('modal-close:firewall-form');
            unset($this->rules);
        } else {
            $this->toastError($message);
        }
    }

    public function deleteRule(string $ruleId)
    {
        Gate::authorize('cloudflare.waf.delete');

        if ($this->cloudflare->deleteFirewallRule($this->zone, $ruleId)) {
            $this->toastSuccess('Firewall rule berhasil dihapus');
            unset($this->rules);
        } else {
            $this->toastError('Gagal menghapus firewall rule');
        }
    }

    public function render()
    {
        return view('nawasara-cloudflare::livewire.pages.firewall.section.table');
    }
}
