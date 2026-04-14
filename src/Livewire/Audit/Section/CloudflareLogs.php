<?php

namespace Nawasara\Cloudflare\Livewire\Audit\Section;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Nawasara\Cloudflare\Services\CloudflareClient;

class CloudflareLogs extends Component
{
    #[Url(except: '')]
    public string $actor = '';

    #[Url(except: '')]
    public string $since = '';

    #[Url(except: '')]
    public string $before = '';

    public int $page = 1;

    protected CloudflareClient $cloudflare;

    public function boot(CloudflareClient $cloudflare)
    {
        $this->cloudflare = $cloudflare;
    }

    public function mount(): void
    {
        if (! $this->since) {
            $this->since = now()->subDays(7)->toDateString();
        }
        if (! $this->before) {
            $this->before = now()->addDay()->toDateString();
        }
    }

    #[Computed]
    public function logs()
    {
        $params = [
            'page' => $this->page,
            'per_page' => 25,
            'since' => $this->since . 'T00:00:00Z',
            'before' => $this->before . 'T23:59:59Z',
        ];

        if ($this->actor) {
            $params['actor.email'] = $this->actor;
        }

        return $this->cloudflare->getAuditLogs($params);
    }

    public function applyFilters(): void
    {
        $this->page = 1;
        unset($this->logs);
    }

    public function previousPage(): void
    {
        $this->page = max(1, $this->page - 1);
        unset($this->logs);
    }

    public function nextPage(): void
    {
        $this->page++;
        unset($this->logs);
    }

    public function render()
    {
        return view('nawasara-cloudflare::livewire.pages.audit.section.cloudflare-logs');
    }
}
