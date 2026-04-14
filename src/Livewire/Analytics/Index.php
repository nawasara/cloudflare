<?php

namespace Nawasara\Cloudflare\Livewire\Analytics;

use Livewire\Component;
use Livewire\Attributes\Url;

class Index extends Component
{
    #[Url(as: 'tab')]
    public string $tab = 'zone';

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, ['zone', 'opd']) ? $tab : 'zone';
    }

    public function render()
    {
        return view('nawasara-cloudflare::livewire.pages.analytics.index')
            ->layout('nawasara-ui::components.layouts.app');
    }
}
