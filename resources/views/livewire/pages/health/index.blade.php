<div>
    <x-nawasara-ui::page.container>
        <x-nawasara-ui::page.title>Health Monitoring</x-nawasara-ui::page.title>

        <div class="mb-6">
            <x-nawasara-ui::tab-switcher
                :items="[
                    ['key' => 'dns', 'label' => 'DNS Endpoints', 'icon' => 'lucide-list-checks'],
                    ['key' => 'zone', 'label' => 'Zone Health', 'icon' => 'lucide-globe'],
                ]"
                :active="$tab"
                wire-method="setTab" />
        </div>

        @if ($tab === 'dns')
            <livewire:nawasara-cloudflare.health.section.dns-health :key="'health-dns'" />
        @else
            <livewire:nawasara-cloudflare.health.section.zone-health :key="'health-zone'" />
        @endif
    </x-nawasara-ui::page.container>
</div>
