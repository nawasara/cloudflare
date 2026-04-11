<?php

namespace Nawasara\Cloudflare\Livewire\Analytics;

use Livewire\Component;

class Index extends Component
{
    public function render()
    {
        return view('nawasara-cloudflare::livewire.pages.analytics.index')
            ->layout('nawasara-ui::components.layouts.app');
    }
}
