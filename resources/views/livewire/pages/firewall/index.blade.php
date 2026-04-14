<div>
    <x-nawasara-ui::page.container>
        <x-nawasara-ui::page.title>Firewall Rules</x-nawasara-ui::page.title>

        <x-slot name="actions">
            <x-nawasara-ui::page.actions>
                <x-nawasara-ui::button wire:click="$dispatch('openCreateFirewall')" color="success"
                    permission="cloudflare.waf.create">
                    <x-slot:icon><x-lucide-plus class="size-4" /></x-slot:icon>
                    Tambah Rule
                </x-nawasara-ui::button>
            </x-nawasara-ui::page.actions>
        </x-slot>

        <livewire:nawasara-cloudflare.firewall.section.table />
    </x-nawasara-ui::page.container>
</div>
