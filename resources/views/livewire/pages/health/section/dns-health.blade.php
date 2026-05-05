<div>
    @php $summary = $this->summary; @endphp

    {{-- Summary Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-6">
        <x-nawasara-ui::stat-card
            label="Healthy"
            :value="$summary['ok']"
            icon="lucide-circle-check"
            color="success"
            :active="$stateFilter === 'ok'"
            wire:click="setStateFilter('ok')" />

        <x-nawasara-ui::stat-card
            label="Warning"
            :value="$summary['warning']"
            icon="lucide-triangle-alert"
            color="warning"
            :active="$stateFilter === 'warning'"
            wire:click="setStateFilter('warning')" />

        <x-nawasara-ui::stat-card
            label="Critical"
            :value="$summary['critical']"
            icon="lucide-octagon-alert"
            color="danger"
            :active="$stateFilter === 'critical'"
            wire:click="setStateFilter('critical')" />

        <x-nawasara-ui::stat-card
            label="Unchecked"
            :value="$summary['unchecked']"
            icon="lucide-circle-dashed"
            color="neutral"
            :active="$stateFilter === 'unchecked'"
            wire:click="setStateFilter('unchecked')" />

        <x-nawasara-ui::stat-card
            label="Total Subdomain"
            :value="$summary['total']"
            icon="lucide-list"
            color="primary" />
    </div>

    {{-- Filter Bar --}}
    @php $currentOpdName = $this->opdList->firstWhere('id', $opdFilter)?->name; @endphp
    <x-nawasara-ui::filter-bar searchPlaceholder="Cari subdomain..." searchModel="search">
        <x-nawasara-ui::filter-dropdown
            :label="$currentOpdName ? 'OPD: ' . $currentOpdName : 'OPD'"
            model="opdFilter" :items="$this->opdOptions" />

        <x-slot:actions>
            <x-nawasara-ui::button color="primary" variant="flat" size="sm"
                wire:click="checkPage"
                wire:confirm="Check HTTP semua record di halaman ini? (~10-15 detik)">
                <x-slot:icon>
                    <x-lucide-radar wire:loading.class="animate-spin" wire:target="checkPage" />
                </x-slot:icon>
                Check Page
            </x-nawasara-ui::button>
        </x-slot:actions>

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
                $stateColor = fn ($s) => match ($s) {
                    'ok' => 'success',
                    'warning' => 'warning',
                    'critical' => 'danger',
                    'unchecked' => 'neutral',
                    default => 'neutral',
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
                        <x-nawasara-ui::badge :color="$stateColor($state)">
                            {{ ucfirst($state) }}
                        </x-nawasara-ui::badge>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        @if ($h && $h->error)
                            <x-nawasara-ui::badge color="danger" class="font-mono" title="{{ $h->error }}">ERR</x-nawasara-ui::badge>
                        @elseif ($h && $h->status_code)
                            @php
                                $code = $h->status_code;
                                $codeColor = match (true) {
                                    $code >= 500 => 'danger',
                                    $code >= 400 && ! in_array($code, [401, 403]) => 'warning',
                                    $code >= 300 => 'primary',
                                    default => 'success',
                                };
                            @endphp
                            <x-nawasara-ui::badge :color="$codeColor" class="font-mono">{{ $code }}</x-nawasara-ui::badge>
                        @else
                            <span class="text-gray-300 dark:text-neutral-600 text-xs">-</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-neutral-400">
                        {{ $h && $h->response_time_ms !== null ? $h->response_time_ms . 'ms' : '-' }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        @if ($h && $h->ssl_error)
                            <span class="text-red-600 dark:text-red-400 text-xs" title="{{ $h->ssl_error }}">SSL error</span>
                        @elseif ($h && $h->ssl_days_remaining !== null)
                            @php
                                $days = $h->ssl_days_remaining;
                                $sslClass = match(true) {
                                    $days < 0 => 'text-red-600 dark:text-red-400',
                                    $days <= 14 => 'text-yellow-600 dark:text-yellow-400',
                                    default => 'text-green-600 dark:text-green-400',
                                };
                            @endphp
                            <span class="text-xs {{ $sslClass }}" title="{{ $h->ssl_issuer }}">{{ $days }}d</span>
                        @else
                            <span class="text-gray-300 dark:text-neutral-600 text-xs">-</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-500 dark:text-neutral-500">
                        {{ $h && $h->checked_at ? $h->checked_at->diffForHumans() : '-' }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                        <x-nawasara-ui::button color="neutral" variant="outline" size="sm"
                            wire:click="checkOne({{ $asset->id }})">
                            <x-slot:icon>
                                <x-lucide-radar wire:loading.class="animate-spin" wire:target="checkOne({{ $asset->id }})" />
                            </x-slot:icon>
                            Check
                        </x-nawasara-ui::button>
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
        Worker scheduler menjalankan <code class="font-mono">cloudflare:health-check</code> setiap 15 menit (HTTP) dan harian jam 02:00 (HTTP+SSL).
        Tombol "Check Page" = manual override HTTP probe untuk halaman ini. Tombol "Check" per-record = HTTP + SSL.
    </p>
</div>
