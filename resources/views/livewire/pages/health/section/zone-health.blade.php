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

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <button wire:click="setFilter('ok')" type="button"
            class="text-left bg-white dark:bg-neutral-800 rounded-xl border p-5 transition-colors
                {{ $stateFilter === 'ok' ? 'border-green-500 ring-2 ring-green-200 dark:ring-green-900/40' : 'border-gray-200 dark:border-neutral-700 hover:border-green-300' }}">
            <div class="flex items-center gap-3">
                <div class="flex-shrink-0 size-10 flex items-center justify-center rounded-lg bg-green-100 dark:bg-green-900/30">
                    <x-lucide-circle-check class="size-5 text-green-600 dark:text-green-400" />
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-neutral-400">Healthy</p>
                    <p class="text-2xl font-bold text-gray-800 dark:text-neutral-200">{{ $summary['ok'] }}</p>
                </div>
            </div>
        </button>

        <button wire:click="setFilter('warning')" type="button"
            class="text-left bg-white dark:bg-neutral-800 rounded-xl border p-5 transition-colors
                {{ $stateFilter === 'warning' ? 'border-yellow-500 ring-2 ring-yellow-200 dark:ring-yellow-900/40' : 'border-gray-200 dark:border-neutral-700 hover:border-yellow-300' }}">
            <div class="flex items-center gap-3">
                <div class="flex-shrink-0 size-10 flex items-center justify-center rounded-lg bg-yellow-100 dark:bg-yellow-900/30">
                    <x-lucide-triangle-alert class="size-5 text-yellow-600 dark:text-yellow-400" />
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-neutral-400">Warning</p>
                    <p class="text-2xl font-bold text-gray-800 dark:text-neutral-200">{{ $summary['warning'] }}</p>
                </div>
            </div>
        </button>

        <button wire:click="setFilter('critical')" type="button"
            class="text-left bg-white dark:bg-neutral-800 rounded-xl border p-5 transition-colors
                {{ $stateFilter === 'critical' ? 'border-red-500 ring-2 ring-red-200 dark:ring-red-900/40' : 'border-gray-200 dark:border-neutral-700 hover:border-red-300' }}">
            <div class="flex items-center gap-3">
                <div class="flex-shrink-0 size-10 flex items-center justify-center rounded-lg bg-red-100 dark:bg-red-900/30">
                    <x-lucide-octagon-alert class="size-5 text-red-600 dark:text-red-400" />
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-neutral-400">Critical</p>
                    <p class="text-2xl font-bold text-gray-800 dark:text-neutral-200">{{ $summary['critical'] }}</p>
                </div>
            </div>
        </button>

        <button wire:click="setFilter('unknown')" type="button"
            class="text-left bg-white dark:bg-neutral-800 rounded-xl border p-5 transition-colors
                {{ $stateFilter === 'unknown' ? 'border-gray-500 ring-2 ring-gray-200 dark:ring-gray-900/40' : 'border-gray-200 dark:border-neutral-700 hover:border-gray-300' }}">
            <div class="flex items-center gap-3">
                <div class="flex-shrink-0 size-10 flex items-center justify-center rounded-lg bg-gray-100 dark:bg-neutral-700">
                    <x-lucide-circle-help class="size-5 text-gray-600 dark:text-neutral-400" />
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-neutral-400">Unknown</p>
                    <p class="text-2xl font-bold text-gray-800 dark:text-neutral-200">{{ $summary['unknown'] }}</p>
                </div>
            </div>
        </button>
    </div>

    <x-nawasara-ui::table :headers="['Domain', 'OPD / PIC', 'Overall', 'SSL Mode', 'Cert Expiry', 'DNSSEC', 'Always Online']"
        :title="'Zones Health (' . count($this->filteredRows) . ' / ' . $summary['total'] . ')'">
        <x-slot:table>
            @php
                $stateClass = fn ($s) => match ($s) {
                    'ok' => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
                    'warning' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300',
                    'critical' => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
                    'unknown' => 'bg-gray-100 text-gray-700 dark:bg-neutral-700 dark:text-neutral-300',
                    default => 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
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
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $stateClass($health['overall']) }}">
                            {{ ucfirst($health['overall']) }}
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-mono {{ $stateClass($checks['ssl_mode']['state']) }}">
                            {{ $checks['ssl_mode']['value'] }}
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs {{ $stateClass($checks['cert_expiry']['state']) }}"
                            @if (!empty($checks['cert_expiry']['hint'])) title="{{ $checks['cert_expiry']['hint'] }}" @endif>
                            {{ $checks['cert_expiry']['value'] }}
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs {{ $stateClass($checks['dnssec']['state']) }}">
                            {{ $checks['dnssec']['value'] }}
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs {{ $stateClass($checks['always_online']['state']) }}">
                            {{ $checks['always_online']['value'] }}
                        </span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="px-6 py-8 text-center text-sm text-gray-500 dark:text-neutral-400">
                        Tidak ada zone yang cocok dengan filter.
                    </td>
                </tr>
            @endforelse
        </x-slot:table>
    </x-nawasara-ui::table>

    <p class="mt-4 text-xs text-gray-500 dark:text-neutral-500">
        Data di-cache 10 menit per zone. Klik Refresh untuk menarik data terbaru.
    </p>
</div>
