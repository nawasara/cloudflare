<div>
    <x-nawasara-ui::page.container>
        <x-nawasara-ui::page.title>Analytics</x-nawasara-ui::page.title>

        <div class="flex gap-1 mb-6 border-b border-gray-200 dark:border-neutral-700">
            <button wire:click="setTab('zone')" type="button"
                class="px-4 py-2.5 text-sm font-medium border-b-2 transition-colors inline-flex items-center gap-2
                    {{ $tab === 'zone'
                        ? 'border-blue-600 text-blue-600 dark:text-blue-400 dark:border-blue-400'
                        : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-neutral-400 dark:hover:text-neutral-200' }}">
                <x-lucide-globe class="size-4" />
                Per Zone
            </button>
            <button wire:click="setTab('opd')" type="button"
                class="px-4 py-2.5 text-sm font-medium border-b-2 transition-colors inline-flex items-center gap-2
                    {{ $tab === 'opd'
                        ? 'border-blue-600 text-blue-600 dark:text-blue-400 dark:border-blue-400'
                        : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-neutral-400 dark:hover:text-neutral-200' }}">
                <x-lucide-building-2 class="size-4" />
                Per OPD
            </button>
        </div>

        @if ($tab === 'zone')
            <livewire:nawasara-cloudflare.analytics.section.overview :key="'analytics-zone'" />
        @else
            <livewire:nawasara-cloudflare.analytics.section.opd-rollup :key="'analytics-opd'" />
        @endif
    </x-nawasara-ui::page.container>
</div>
