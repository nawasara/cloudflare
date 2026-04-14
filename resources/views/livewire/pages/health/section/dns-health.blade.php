<div>
    @php $summary = $this->summary; @endphp

    {{-- Summary Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-6">
        <button wire:click="setStateFilter('ok')" type="button"
            class="text-left bg-white dark:bg-neutral-800 rounded-xl border p-4 transition-colors
                {{ $stateFilter === 'ok' ? 'border-green-500 ring-2 ring-green-200 dark:ring-green-900/40' : 'border-gray-200 dark:border-neutral-700 hover:border-green-300' }}">
            <div class="flex items-center gap-2">
                <x-lucide-circle-check class="size-5 text-green-600 dark:text-green-400" />
                <div>
                    <p class="text-xs text-gray-500 dark:text-neutral-400">Healthy</p>
                    <p class="text-xl font-bold text-gray-800 dark:text-neutral-200">{{ $summary['ok'] }}</p>
                </div>
            </div>
        </button>

        <button wire:click="setStateFilter('warning')" type="button"
            class="text-left bg-white dark:bg-neutral-800 rounded-xl border p-4 transition-colors
                {{ $stateFilter === 'warning' ? 'border-yellow-500 ring-2 ring-yellow-200 dark:ring-yellow-900/40' : 'border-gray-200 dark:border-neutral-700 hover:border-yellow-300' }}">
            <div class="flex items-center gap-2">
                <x-lucide-triangle-alert class="size-5 text-yellow-600 dark:text-yellow-400" />
                <div>
                    <p class="text-xs text-gray-500 dark:text-neutral-400">Warning</p>
                    <p class="text-xl font-bold text-gray-800 dark:text-neutral-200">{{ $summary['warning'] }}</p>
                </div>
            </div>
        </button>

        <button wire:click="setStateFilter('critical')" type="button"
            class="text-left bg-white dark:bg-neutral-800 rounded-xl border p-4 transition-colors
                {{ $stateFilter === 'critical' ? 'border-red-500 ring-2 ring-red-200 dark:ring-red-900/40' : 'border-gray-200 dark:border-neutral-700 hover:border-red-300' }}">
            <div class="flex items-center gap-2">
                <x-lucide-octagon-alert class="size-5 text-red-600 dark:text-red-400" />
                <div>
                    <p class="text-xs text-gray-500 dark:text-neutral-400">Critical</p>
                    <p class="text-xl font-bold text-gray-800 dark:text-neutral-200">{{ $summary['critical'] }}</p>
                </div>
            </div>
        </button>

        <button wire:click="setStateFilter('unchecked')" type="button"
            class="text-left bg-white dark:bg-neutral-800 rounded-xl border p-4 transition-colors
                {{ $stateFilter === 'unchecked' ? 'border-gray-500 ring-2 ring-gray-200 dark:ring-gray-900/40' : 'border-gray-200 dark:border-neutral-700 hover:border-gray-300' }}">
            <div class="flex items-center gap-2">
                <x-lucide-circle-dashed class="size-5 text-gray-600 dark:text-neutral-400" />
                <div>
                    <p class="text-xs text-gray-500 dark:text-neutral-400">Unchecked</p>
                    <p class="text-xl font-bold text-gray-800 dark:text-neutral-200">{{ $summary['unchecked'] }}</p>
                </div>
            </div>
        </button>

        <div class="bg-blue-50 dark:bg-blue-900/20 rounded-xl border border-blue-200 dark:border-blue-800 p-4">
            <div class="flex items-center gap-2">
                <x-lucide-list class="size-5 text-blue-600 dark:text-blue-400" />
                <div>
                    <p class="text-xs text-blue-700 dark:text-blue-300">Total Subdomain</p>
                    <p class="text-xl font-bold text-blue-800 dark:text-blue-200">{{ $summary['total'] }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Filter Bar --}}
    <x-nawasara-ui::filter-bar searchPlaceholder="Cari subdomain..." searchModel="search">
        <select wire:model.live="opdFilter"
            class="py-2 px-3 text-sm border-gray-200 rounded-lg dark:bg-neutral-800 dark:border-neutral-700 dark:text-white min-w-[200px]">
            <option value="">Semua OPD</option>
            @foreach ($this->opdList as $opd)
                <option value="{{ $opd->id }}">{{ $opd->code }} - {{ $opd->name }}</option>
            @endforeach
        </select>

        <button wire:click="checkPage" type="button"
            wire:confirm="Check HTTP semua record di halaman ini? (~10-15 detik)"
            class="py-2 px-3 text-sm font-medium rounded-lg border border-blue-200 bg-blue-50 text-blue-700 hover:bg-blue-100 dark:bg-blue-900/20 dark:border-blue-800 dark:text-blue-300 dark:hover:bg-blue-900/40 inline-flex items-center gap-1.5">
            <x-lucide-radar class="size-4" wire:loading.class="animate-spin" wire:target="checkPage" />
            Check Page
        </button>

        <x-slot:chips>
            @if ($stateFilter)
                <x-nawasara-ui::filter-chip :label="'State: ' . ucfirst($stateFilter)" model="stateFilter" />
            @endif
            @if ($opdFilter)
                <x-nawasara-ui::filter-chip label="OPD filter aktif" model="opdFilter" />
            @endif
            @if ($search)
                <x-nawasara-ui::filter-chip label="Cari: {{ $search }}" model="search" />
            @endif
        </x-slot:chips>
    </x-nawasara-ui::filter-bar>

    <x-nawasara-ui::table :headers="['Subdomain', 'OPD / PIC', 'State', 'HTTP', 'Response', 'SSL', 'Checked', '']"
        :title="'DNS Endpoints (' . count($this->rows) . ' di halaman ini)'">
        <x-slot:table>
            @php
                $stateClass = fn ($s) => match ($s) {
                    'ok' => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
                    'warning' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300',
                    'critical' => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
                    'unchecked' => 'bg-gray-100 text-gray-500 dark:bg-neutral-700 dark:text-neutral-400',
                    default => 'bg-gray-100 text-gray-700 dark:bg-neutral-700 dark:text-neutral-300',
                };
            @endphp
            @forelse ($this->rows as $row)
                @php
                    $asset = $row['asset'];
                    $h = $row['health'];
                    $state = $row['state'];
                @endphp
                <tr>
                    <td class="px-6 py-4 text-sm font-medium text-gray-800 dark:text-neutral-200 max-w-xs truncate">
                        {{ $asset->identifier }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        @if ($asset->opd)
                            <div class="flex flex-col">
                                <span class="text-gray-800 dark:text-neutral-200">{{ $asset->opd->name }}</span>
                                @if ($asset->pic)
                                    <span class="text-xs text-gray-500 dark:text-neutral-400">PIC: {{ $asset->pic->name }}</span>
                                @endif
                            </div>
                        @else
                            <span class="text-gray-400 dark:text-neutral-500 text-xs">-</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $stateClass($state) }}">
                            {{ ucfirst($state) }}
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        @if ($h && !empty($h['error']))
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-mono bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400"
                                title="{{ $h['error'] }}">ERR</span>
                        @elseif ($h && isset($h['status_code']))
                            @php
                                $code = $h['status_code'];
                                $codeClass = match(true) {
                                    $code >= 500 => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
                                    $code >= 400 => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
                                    $code >= 300 => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
                                    default => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
                                };
                            @endphp
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-mono {{ $codeClass }}">{{ $code }}</span>
                        @else
                            <span class="text-gray-300 dark:text-neutral-600 text-xs">-</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-neutral-400">
                        {{ $h && isset($h['response_time_ms']) ? $h['response_time_ms'] . 'ms' : '-' }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        @if ($h && !empty($h['ssl_error']))
                            <span class="text-red-600 dark:text-red-400 text-xs" title="{{ $h['ssl_error'] }}">SSL error</span>
                        @elseif ($h && isset($h['ssl_days_remaining']))
                            @php
                                $days = $h['ssl_days_remaining'];
                                $sslClass = match(true) {
                                    $days < 0 => 'text-red-600 dark:text-red-400',
                                    $days <= 14 => 'text-yellow-600 dark:text-yellow-400',
                                    default => 'text-green-600 dark:text-green-400',
                                };
                            @endphp
                            <span class="text-xs {{ $sslClass }}" title="{{ $h['ssl_issuer'] ?? '' }}">{{ $days }}d</span>
                        @else
                            <span class="text-gray-300 dark:text-neutral-600 text-xs">-</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-500 dark:text-neutral-500">
                        {{ $h ? \Carbon\Carbon::parse($h['checked_at'])->diffForHumans() : '-' }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                        <button wire:click="checkOne({{ $asset->id }})" type="button"
                            class="px-2.5 py-1 text-xs font-medium rounded-md border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-700 inline-flex items-center gap-1">
                            <x-lucide-radar class="size-3" wire:loading.class="animate-spin" wire:target="checkOne({{ $asset->id }})" />
                            Check
                        </button>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="px-6 py-8 text-center text-sm text-gray-500 dark:text-neutral-400">
                        @if ($summary['total'] === 0)
                            Belum ada subdomain di registry. Lakukan Sync ke Registry di halaman DNS Records.
                        @else
                            Tidak ada record yang cocok dengan filter.
                        @endif
                    </td>
                </tr>
            @endforelse
        </x-slot:table>

        <x-slot:footer>
            {{ $this->items->links() }}
        </x-slot:footer>
    </x-nawasara-ui::table>

    <p class="mt-4 text-xs text-gray-500 dark:text-neutral-500">
        Check Page = HTTP probe only (cepat). Tombol Check per record = HTTP + SSL certificate (butuh ~3-5 detik).
        Data di-cache 30 menit.
    </p>
</div>
