<?php

namespace Nawasara\Cloudflare\Livewire\Dns;

use Livewire\Component;

class Index extends Component
{
    public function render()
    {
        return view('nawasara-cloudflare::livewire.pages.dns.index')
            ->layout('nawasara-ui::components.layouts.app');
    }
}
