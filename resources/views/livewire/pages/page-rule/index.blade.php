<div>
    <x-nawasara-ui::page.container>
        <x-nawasara-ui::page.title>Page Rules</x-nawasara-ui::page.title>

        <x-slot name="actions">
            <x-nawasara-ui::page.actions>
                <x-nawasara-ui::button color="success" wire:click="$dispatch('openCreatePageRule')"
                    @click="$dispatch('open-modal', 'pagerule-form')"
                    permission="cloudflare.pagerule.create">
                    <x-slot:icon><x-lucide-plus /></x-slot:icon>
                    Tambah Rule
                </x-nawasara-ui::button>
            </x-nawasara-ui::page.actions>
        </x-slot>

        <p class="text-xs text-gray-500 dark:text-neutral-400 mb-2">
            Cloudflare sedang memigrasikan Page Rules ke
            <a href="https://developers.cloudflare.com/rules/" target="_blank" rel="noopener"
                class="text-emerald-700 dark:text-emerald-400 hover:underline font-medium">Rules engine baru</a>
            (Configuration / Transform / Cache / Redirect Rules). Page Rules masih bekerja tapi tergolong legacy.
        </p>

        <livewire:nawasara-cloudflare.page-rule.section.table />
    </x-nawasara-ui::page.container>
</div>
