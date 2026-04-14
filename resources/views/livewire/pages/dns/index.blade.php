<div>
    <x-nawasara-ui::page.container>
        <x-nawasara-ui::page.title>DNS Records</x-nawasara-ui::page.title>

        <x-slot name="actions">
            <x-nawasara-ui::page.actions>
                <x-nawasara-ui::button wire:click="$dispatch('openCreateDns')" color="success"
                    permission="cloudflare.dns.create">
                    <x-slot:icon><x-lucide-plus class="size-4" /></x-slot:icon>
                    Tambah Record
                </x-nawasara-ui::button>
            </x-nawasara-ui::page.actions>
        </x-slot>

        <livewire:nawasara-cloudflare.dns.section.table />
    </x-nawasara-ui::page.container>
</div>
