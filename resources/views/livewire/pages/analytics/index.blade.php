<div>
    <x-nawasara-ui::page.container>
        <x-nawasara-ui::page.title>Analytics</x-nawasara-ui::page.title>

        <div class="mb-6">
            <x-nawasara-ui::tab-switcher
                :items="[
                    ['key' => 'zone', 'label' => 'Per Zone', 'icon' => 'lucide-globe'],
                    ['key' => 'opd', 'label' => 'Per OPD', 'icon' => 'lucide-building-2'],
                ]"
                :active="$tab"
                wire-method="setTab" />
        </div>

        @if ($tab === 'zone')
            <livewire:nawasara-cloudflare.analytics.section.overview :key="'analytics-zone'" />
        @else
            <livewire:nawasara-cloudflare.analytics.section.opd-rollup :key="'analytics-opd'" />
        @endif
    </x-nawasara-ui::page.container>
</div>
