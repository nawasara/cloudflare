<?php

namespace Nawasara\Cloudflare\Livewire\Health;

use Livewire\Component;
use Livewire\Attributes\Url;

class Index extends Component
{
    #[Url(as: 'tab')]
    public string $tab = 'dns';

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, ['zone', 'dns']) ? $tab : 'dns';
    }

    public function render()
    {
        return view('nawasara-cloudflare::livewire.pages.health.index')
            ->layout('nawasara-ui::components.layouts.app');
    }
}
