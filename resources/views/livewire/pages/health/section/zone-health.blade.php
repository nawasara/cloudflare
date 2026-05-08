<div>
    <div class="flex items-center justify-end mb-4">
        <x-nawasara-ui::button color="neutral" variant="outline" size="sm" wire:click="refreshAll">
            <x-slot:icon>
                <x-lucide-refresh-cw wire:loading.class="animate-spin" wire:target="refreshAll" />
            </x-slot:icon>
            Refresh
        </x-nawasara-ui::button>
    </div>

    @php $summary = $this->summary; @endphp

    {{-- Filter cards — clickable untuk filter table.
         accent border-left kasih hierarchy konsisten dgn hero stats di
         pages lain. Active state (ring) tetap signal "filter aktif". --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <x-nawasara-ui::stat-card
            label="Healthy"
            :value="$summary['ok']"
            icon="lucide-circle-check"
            color="success"
            :active="$stateFilter === 'ok'"
            accent
            wire:click="setFilter('ok')" />

        <x-nawasara-ui::stat-card
            label="Warning"
            :value="$summary['warning']"
            icon="lucide-triangle-alert"
            color="warning"
            :active="$stateFilter === 'warning'"
            accent
            wire:click="setFilter('warning')" />

        <x-nawasara-ui::stat-card
            label="Critical"
            :value="$summary['critical']"
            icon="lucide-octagon-alert"
            color="danger"
            :active="$stateFilter === 'critical'"
            accent
            wire:click="setFilter('critical')" />

        <x-nawasara-ui::stat-card
            label="Unknown"
            :value="$summary['unknown']"
            icon="lucide-circle-help"
            color="neutral"
            :active="$stateFilter === 'unknown'"
            accent
            wire:click="setFilter('unknown')" />
    </div>

    <x-nawasara-ui::table :headers="['Domain', 'OPD / PIC', 'Overall', 'SSL Mode', 'Cert Expiry', 'DNSSEC', 'Always Online']"
        :title="'Zones Health (' . count($this->filteredRows) . ' / ' . $summary['total'] . ')'">
        <x-slot:table>
            @php
                $stateColor = fn ($s) => match ($s) {
                    'ok' => 'success',
                    'warning' => 'warning',
                    'critical' => 'danger',
                    'unknown' => 'neutral',
                    default => 'primary',
                };
            @endphp
            @forelse ($this->filteredRows as $row)
                @php
                    $health = $row['health'];
                    $zone = $row['zone'];
                    $checks = $health['checks'];
                @endphp
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-800 dark:text-neutral-200">
                        {{ $zone['name'] }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        @if ($row['opd'])
                            <div class="flex flex-col">
                                <span class="text-gray-800 dark:text-neutral-200">{{ $row['opd']->name }}</span>
                                @if ($row['pic'])
                                    <span class="text-xs text-gray-500 dark:text-neutral-400">PIC: {{ $row['pic']->name }}</span>
                                @endif
                            </div>
                        @else
                            <span class="text-gray-400 dark:text-neutral-500 text-xs">-</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        <x-nawasara-ui::badge :color="$stateColor($health['overall'])">
                            {{ ucfirst($health['overall']) }}
                        </x-nawasara-ui::badge>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        <x-nawasara-ui::badge :color="$stateColor($checks['ssl_mode']['state'])" class="font-mono">
                            {{ $checks['ssl_mode']['value'] }}
                        </x-nawasara-ui::badge>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        {{-- Pre-compute hint supaya bisa dipasang via plain
                             `title=""` HTML attribute. Hindari `:title="..."`
                             yang di Alpine subtree akan di-treat sebagai
                             x-bind:title dan trigger ReferenceError. Hindari
                             juga @if/@endif inline di attribute list component
                             tag (Blade parser eror "unexpected endif"). --}}
                        @php $expiryHint = $checks['cert_expiry']['hint'] ?? ''; @endphp
                        <x-nawasara-ui::badge
                            :color="$stateColor($checks['cert_expiry']['state'])"
                            title="{{ $expiryHint }}">
                            {{ $checks['cert_expiry']['value'] }}
                        </x-nawasara-ui::badge>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        <x-nawasara-ui::badge :color="$stateColor($checks['dnssec']['state'])">
                            {{ $checks['dnssec']['value'] }}
                        </x-nawasara-ui::badge>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        <x-nawasara-ui::badge :color="$stateColor($checks['always_online']['state'])">
                            {{ $checks['always_online']['value'] }}
                        </x-nawasara-ui::badge>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">
                        <x-nawasara-ui::empty-state
                            icon="lucide-search-x"
                            title="Tidak ada zone yang cocok"
                            description="Coba klik salah satu kategori (Healthy/Warning/Critical) di atas atau Refresh."
                            variant="filter"
                            inline />
                    </td>
                </tr>
            @endforelse
        </x-slot:table>
    </x-nawasara-ui::table>

    <p class="mt-4 text-xs text-gray-500 dark:text-neutral-500">
        Data di-cache 10 menit per zone. Klik Refresh untuk menarik data terbaru.
    </p>
</div>
