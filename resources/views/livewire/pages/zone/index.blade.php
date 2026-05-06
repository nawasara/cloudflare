<div>
    <x-nawasara-ui::page.container>
        <x-nawasara-ui::page.title>Cloudflare Zones</x-nawasara-ui::page.title>

        {{-- Hero stats — derive di Index component supaya angka KPI selalu
             reflect total realitas, bukan ter-filter search/status table. --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            @foreach ($this->stats as $stat)
                <x-nawasara-ui::stat-card
                    :label="$stat['label']"
                    :value="$stat['value']"
                    :icon="$stat['icon']"
                    :color="$stat['color']"
                    :description="$stat['description'] ?? null"
                    accent />
            @endforeach
        </div>

        <livewire:nawasara-cloudflare.zone.section.table />
    </x-nawasara-ui::page.container>
</div>
