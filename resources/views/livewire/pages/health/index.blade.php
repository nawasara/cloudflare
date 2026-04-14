<div>
    <x-nawasara-ui::page.container>
        <x-nawasara-ui::page.title>Health Monitoring</x-nawasara-ui::page.title>

        <div class="flex gap-1 mb-6 border-b border-gray-200 dark:border-neutral-700">
            <button wire:click="setTab('dns')" type="button"
                class="px-4 py-2.5 text-sm font-medium border-b-2 transition-colors inline-flex items-center gap-2
                    {{ $tab === 'dns'
                        ? 'border-blue-600 text-blue-600 dark:text-blue-400 dark:border-blue-400'
                        : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-neutral-400 dark:hover:text-neutral-200' }}">
                <x-lucide-list-checks class="size-4" />
                DNS Endpoints
            </button>
            <button wire:click="setTab('zone')" type="button"
                class="px-4 py-2.5 text-sm font-medium border-b-2 transition-colors inline-flex items-center gap-2
                    {{ $tab === 'zone'
                        ? 'border-blue-600 text-blue-600 dark:text-blue-400 dark:border-blue-400'
                        : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-neutral-400 dark:hover:text-neutral-200' }}">
                <x-lucide-globe class="size-4" />
                Zone Health
            </button>
        </div>

        @if ($tab === 'dns')
            <livewire:nawasara-cloudflare.health.section.dns-health :key="'health-dns'" />
        @else
            <livewire:nawasara-cloudflare.health.section.zone-health :key="'health-zone'" />
        @endif
    </x-nawasara-ui::page.container>
</div>
