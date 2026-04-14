<?php

namespace Nawasara\Cloudflare\Livewire\PageRule\Section;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Nawasara\Cloudflare\Services\CloudflareClient;

class Table extends Component
{
    /** Action type → human label. Single-action MVP. */
    public const ACTION_TYPES = [
        'always_use_https' => 'Always Use HTTPS',
        'forwarding_url' => 'Forwarding URL (Redirect)',
        'cache_level' => 'Cache Level',
        'browser_cache_ttl' => 'Browser Cache TTL',
        'ssl' => 'SSL',
        'disable_security' => 'Disable Security',
        'disable_apps' => 'Disable Apps',
        'disable_performance' => 'Disable Performance',
    ];

    public const CACHE_LEVELS = [
        'bypass' => 'Bypass',
        'basic' => 'Basic',
        'simplified' => 'Simplified',
        'aggressive' => 'Aggressive',
        'cache_everything' => 'Cache Everything',
    ];

    public const SSL_MODES = [
        'off' => 'Off',
        'flexible' => 'Flexible',
        'full' => 'Full',
        'strict' => 'Strict',
    ];

    public const FORWARDING_STATUS_CODES = [
        301 => '301 Permanent',
        302 => '302 Temporary',
    ];

    #[Url(except: '')]
    public string $zone = '';

    // Form modal
    public bool $showForm = false;
    public ?string $editingId = null;
    public string $formTarget = '';
    public string $formActionType = 'always_use_https';
    public string $formActionValue = '';
    public int $formForwardingStatus = 301;
    public int $formPriority = 1;
    public string $formStatus = 'active';

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

        return $this->cloudflare->getPageRules($this->zone);
    }

    public function updatedZone()
    {
        unset($this->rules);
    }

    #[On('openCreatePageRule')]
    public function openCreate()
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function openEdit(string $ruleId)
    {
        $rule = collect($this->rules)->firstWhere('id', $ruleId);
        if (! $rule) {
            return;
        }

        $this->editingId = $ruleId;
        $this->formTarget = $rule['targets'][0]['constraint']['value'] ?? '';
        $this->formPriority = $rule['priority'] ?? 1;
        $this->formStatus = $rule['status'] ?? 'active';

        $action = $rule['actions'][0] ?? [];
        $this->formActionType = $action['id'] ?? 'always_use_https';
        $value = $action['value'] ?? null;

        if ($this->formActionType === 'forwarding_url' && is_array($value)) {
            $this->formActionValue = $value['url'] ?? '';
            $this->formForwardingStatus = (int) ($value['status_code'] ?? 301);
        } elseif (is_scalar($value)) {
            $this->formActionValue = (string) $value;
        } else {
            $this->formActionValue = '';
        }

        $this->showForm = true;
    }

    public function save()
    {
        $this->validate([
            'formTarget' => 'required|string|max:500',
            'formActionType' => 'required|string',
            'formPriority' => 'integer|min:1|max:1000',
        ]);

        $action = $this->buildAction();
        if ($action === null) {
            toaster_error('Action value tidak valid');
            return;
        }

        $payload = [
            'targets' => [[
                'target' => 'url',
                'constraint' => ['operator' => 'matches', 'value' => $this->formTarget],
            ]],
            'actions' => [$action],
            'priority' => $this->formPriority,
            'status' => $this->formStatus,
        ];

        if ($this->editingId) {
            $ok = $this->cloudflare->updatePageRule($this->zone, $this->editingId, $payload);
            $msg = $ok ? 'Page rule berhasil diupdate' : 'Gagal mengupdate page rule';
        } else {
            $result = $this->cloudflare->createPageRule($this->zone, $payload);
            $ok = $result !== null;
            $msg = $ok ? 'Page rule berhasil dibuat' : 'Gagal membuat page rule (cek limit plan & konflik priority)';
        }

        if ($ok) {
            toaster_success($msg);
            $this->showForm = false;
            unset($this->rules);
        } else {
            toaster_error($msg);
        }
    }

    protected function buildAction(): ?array
    {
        $type = $this->formActionType;
        $val = $this->formActionValue;

        return match ($type) {
            'always_use_https',
            'disable_security',
            'disable_apps',
            'disable_performance' => ['id' => $type],

            'forwarding_url' => $val === '' ? null : [
                'id' => 'forwarding_url',
                'value' => [
                    'url' => $val,
                    'status_code' => $this->formForwardingStatus,
                ],
            ],

            'cache_level' => $val === '' ? null : [
                'id' => 'cache_level',
                'value' => $val,
            ],

            'ssl' => $val === '' ? null : [
                'id' => 'ssl',
                'value' => $val,
            ],

            'browser_cache_ttl' => $val === '' ? null : [
                'id' => 'browser_cache_ttl',
                'value' => (int) $val,
            ],

            default => null,
        };
    }

    public function toggleStatus(string $ruleId)
    {
        $rule = collect($this->rules)->firstWhere('id', $ruleId);
        if (! $rule) {
            return;
        }

        $newStatus = ($rule['status'] ?? 'active') === 'active' ? 'disabled' : 'active';

        if ($this->cloudflare->togglePageRule($this->zone, $ruleId, $newStatus)) {
            toaster_success("Rule diubah ke {$newStatus}");
            unset($this->rules);
        } else {
            toaster_error('Gagal mengubah status rule');
        }
    }

    public function deleteRule(string $ruleId)
    {
        if ($this->cloudflare->deletePageRule($this->zone, $ruleId)) {
            toaster_success('Page rule berhasil dihapus');
            unset($this->rules);
        } else {
            toaster_error('Gagal menghapus page rule');
        }
    }

    protected function resetForm(): void
    {
        $this->editingId = null;
        $this->formTarget = '';
        $this->formActionType = 'always_use_https';
        $this->formActionValue = '';
        $this->formForwardingStatus = 301;
        $this->formPriority = 1;
        $this->formStatus = 'active';
    }

    public function render()
    {
        return view('nawasara-cloudflare::livewire.pages.page-rule.section.table');
    }
}
