<div>
    <x-nawasara-ui::filter-bar searchPlaceholder="Cari domain..." searchModel="search">
        <button wire:click="refreshZones" type="button"
            class="py-2 px-3 text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-800 hover:bg-gray-50 dark:bg-neutral-800 dark:border-neutral-700 dark:text-white dark:hover:bg-neutral-700 inline-flex items-center gap-1.5">
            <x-lucide-refresh-cw class="size-4" wire:loading.class="animate-spin" wire:target="refreshZones" />
            Refresh
        </button>

        <button wire:click="syncRegistry" type="button"
            wire:confirm="Sinkronkan daftar zone Cloudflare ke Registry aset?"
            class="py-2 px-3 text-sm font-medium rounded-lg border border-blue-200 bg-blue-50 text-blue-700 hover:bg-blue-100 dark:bg-blue-900/20 dark:border-blue-800 dark:text-blue-300 dark:hover:bg-blue-900/40 inline-flex items-center gap-1.5">
            <x-lucide-link class="size-4" wire:loading.class="animate-spin" wire:target="syncRegistry" />
            Sync ke Registry
        </button>

        <x-slot:chips>
            @if ($search)
                <x-nawasara-ui::filter-chip label="Cari: {{ $search }}" model="search" />
            @endif
        </x-slot:chips>
    </x-nawasara-ui::filter-bar>

    <x-nawasara-ui::table :headers="['Domain', 'OPD / PIC', 'Status', 'Plan', 'SSL', 'Name Servers', '']" title="Zones ({{ count($this->zones) }} domain)">
        <x-slot:table>
            @forelse ($this->zones as $zone)
                @php $asset = $this->assetMap[$zone['id']] ?? null; @endphp
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-800 dark:text-neutral-200">
                        {{ $zone['name'] ?? '-' }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        @if ($asset && $asset->opd)
                            <div class="flex flex-col">
                                <span class="font-medium text-gray-800 dark:text-neutral-200">{{ $asset->opd->name }}</span>
                                @if ($asset->pic)
                                    <span class="text-xs text-gray-500 dark:text-neutral-400">PIC: {{ $asset->pic->name }}</span>
                                @endif
                            </div>
                        @elseif ($asset)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-50 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400">
                                Belum ditetapkan
                            </span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500 dark:bg-neutral-700 dark:text-neutral-400">
                                Belum di-sync
                            </span>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        @php
                            $statusClass = match($zone['status'] ?? '') {
                                'active' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
                                'pending' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
                                'moved' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
                                default => 'bg-gray-100 text-gray-800 dark:bg-neutral-700 dark:text-neutral-300',
                            };
                        @endphp
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusClass }}">
                            {{ ucfirst($zone['status'] ?? 'unknown') }}
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-neutral-400">
                        {{ ucfirst($zone['plan']['name'] ?? '-') }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-neutral-400">
                        {{ strtoupper($zone['ssl']['status'] ?? '-') }}
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-500 dark:text-neutral-400 max-w-xs">
                        @if (!empty($zone['name_servers']))
                            <div class="space-y-0.5">
                                @foreach ($zone['name_servers'] as $ns)
                                    <div class="text-xs font-mono">{{ $ns }}</div>
                                @endforeach
                            </div>
                        @else
                            -
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                        <x-nawasara-ui::dropdown-menu-action :id="$zone['id']" :items="[
                            ['type' => 'click', 'label' => 'Detail', 'wire:click' => 'openDetail(\'' . $zone['id'] . '\')', 'icon' => 'lucide-eye'],
                            ['type' => 'click', 'label' => 'Purge Cache', 'wire:click' => 'openPurge(\'' . $zone['id'] . '\', \'' . $zone['name'] . '\')', 'icon' => 'lucide-trash-2'],
                            ['type' => 'link', 'label' => 'DNS Records', 'href' => url('nawasara-cloudflare/dns?zone=' . $zone['id']), 'icon' => 'lucide-list', 'navigate' => true],
                        ]" />
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="px-6 py-8 text-center text-sm text-gray-500 dark:text-neutral-400">
                        Tidak ada zone ditemukan.
                    </td>
                </tr>
            @endforelse
        </x-slot:table>
    </x-nawasara-ui::table>

    {{-- Detail Modal --}}
    <x-nawasara-ui::modal wire:model="showDetail" maxWidth="2xl" :title="$detailZone['name'] ?? 'Zone Detail'">
        @if ($detailZone)
            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div><span class="text-gray-500 dark:text-neutral-400">Domain:</span> <span class="font-medium text-gray-800 dark:text-neutral-200">{{ $detailZone['name'] }}</span></div>
                    <div><span class="text-gray-500 dark:text-neutral-400">Status:</span> <span class="font-medium text-gray-800 dark:text-neutral-200">{{ ucfirst($detailZone['status'] ?? '-') }}</span></div>
                    <div><span class="text-gray-500 dark:text-neutral-400">Plan:</span> <span class="font-medium text-gray-800 dark:text-neutral-200">{{ $detailZone['plan']['name'] ?? '-' }}</span></div>
                    <div><span class="text-gray-500 dark:text-neutral-400">Type:</span> <span class="font-medium text-gray-800 dark:text-neutral-200">{{ ucfirst($detailZone['type'] ?? '-') }}</span></div>
                </div>

                {{-- SSL Mode --}}
                <div>
                    <h4 class="font-semibold text-gray-700 dark:text-neutral-300 mb-2">SSL/TLS Mode</h4>
                    <div class="flex gap-2">
                        @foreach (['off', 'flexible', 'full', 'strict'] as $mode)
                            <button wire:click="setSslMode('{{ $mode }}')"
                                class="px-3 py-1.5 text-xs font-medium rounded-lg border transition-colors
                                    {{ $detailSsl === $mode
                                        ? 'bg-blue-600 text-white border-blue-600'
                                        : 'bg-white text-gray-700 border-gray-200 hover:bg-gray-50 dark:bg-neutral-800 dark:text-neutral-300 dark:border-neutral-700 dark:hover:bg-neutral-700' }}">
                                {{ ucfirst($mode) }}
                            </button>
                        @endforeach
                    </div>
                </div>

                {{-- Security Level / Under Attack Mode --}}
                <div>
                    <h4 class="font-semibold text-gray-700 dark:text-neutral-300 mb-2">Security Level</h4>

                    {{-- Under Attack Mode Toggle --}}
                    <div class="flex items-center justify-between p-3 rounded-lg mb-3
                        {{ $detailSecurityLevel === 'under_attack'
                            ? 'bg-red-50 border border-red-200 dark:bg-red-900/20 dark:border-red-800'
                            : 'bg-gray-50 border border-gray-200 dark:bg-neutral-700/50 dark:border-neutral-600' }}">
                        <div class="flex items-center gap-2">
                            <x-lucide-shield-alert class="size-5 {{ $detailSecurityLevel === 'under_attack' ? 'text-red-600 dark:text-red-400' : 'text-gray-400' }}" />
                            <div>
                                <p class="text-sm font-medium {{ $detailSecurityLevel === 'under_attack' ? 'text-red-700 dark:text-red-300' : 'text-gray-700 dark:text-neutral-300' }}">
                                    Under Attack Mode
                                </p>
                                <p class="text-xs {{ $detailSecurityLevel === 'under_attack' ? 'text-red-500 dark:text-red-400' : 'text-gray-400 dark:text-neutral-500' }}">
                                    {{ $detailSecurityLevel === 'under_attack' ? 'Aktif — semua visitor melewati JS challenge' : 'Nonaktif — aktifkan saat terjadi DDoS attack' }}
                                </p>
                            </div>
                        </div>
                        <button
                            wire:click="setSecurityLevel('{{ $detailSecurityLevel === 'under_attack' ? 'medium' : 'under_attack' }}')"
                            wire:confirm="{{ $detailSecurityLevel === 'under_attack' ? 'Nonaktifkan Under Attack Mode?' : 'Aktifkan Under Attack Mode? Semua visitor akan melewati JS challenge selama 5 detik.' }}"
                            class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out
                                {{ $detailSecurityLevel === 'under_attack' ? 'bg-red-600' : 'bg-gray-200 dark:bg-neutral-600' }}">
                            <span class="pointer-events-none inline-block size-5 rounded-full bg-white shadow transform transition duration-200 ease-in-out
                                {{ $detailSecurityLevel === 'under_attack' ? 'translate-x-5' : 'translate-x-0' }}"></span>
                        </button>
                    </div>

                    {{-- Security Level Buttons --}}
                    <div class="flex flex-wrap gap-2">
                        @foreach (['off' => 'Off', 'essentially_off' => 'Essentially Off', 'low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'under_attack' => 'Under Attack'] as $level => $label)
                            <button wire:click="setSecurityLevel('{{ $level }}')"
                                @if($level === 'under_attack') wire:confirm="Aktifkan Under Attack Mode?" @endif
                                class="px-3 py-1.5 text-xs font-medium rounded-lg border transition-colors
                                    {{ $detailSecurityLevel === $level
                                        ? ($level === 'under_attack'
                                            ? 'bg-red-600 text-white border-red-600'
                                            : 'bg-blue-600 text-white border-blue-600')
                                        : 'bg-white text-gray-700 border-gray-200 hover:bg-gray-50 dark:bg-neutral-800 dark:text-neutral-300 dark:border-neutral-700 dark:hover:bg-neutral-700' }}">
                                {{ $label }}
                            </button>
                        @endforeach
                    </div>
                </div>

                {{-- Name Servers --}}
                @if (!empty($detailZone['name_servers']))
                    <div>
                        <h4 class="font-semibold text-gray-700 dark:text-neutral-300 mb-2">Name Servers</h4>
                        <div class="space-y-1">
                            @foreach ($detailZone['name_servers'] as $ns)
                                <div class="text-sm font-mono text-gray-600 dark:text-neutral-400 bg-gray-50 dark:bg-neutral-700/50 px-3 py-1.5 rounded">{{ $ns }}</div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <x-slot:footer>
                <button wire:click="closeDetail" class="py-2 px-4 text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-800 hover:bg-gray-50 dark:bg-neutral-800 dark:border-neutral-700 dark:text-white">Tutup</button>
            </x-slot:footer>
        @endif
    </x-nawasara-ui::modal>

    {{-- Purge Cache Modal --}}
    <x-nawasara-ui::modal wire:model="showPurge" maxWidth="md" :title="'Purge Cache: ' . $purgeZoneName">
        <div class="space-y-4">
            <div class="flex gap-3">
                <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-neutral-300">
                    <input type="radio" wire:model.live="purgeType" value="all" class="text-blue-600">
                    Purge Everything
                </label>
                <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-neutral-300">
                    <input type="radio" wire:model.live="purgeType" value="urls" class="text-blue-600">
                    Custom URLs
                </label>
            </div>

            @if ($purgeType === 'urls')
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-neutral-300 mb-1">URLs (satu per baris)</label>
                    <textarea wire:model="purgeUrls" rows="4"
                        class="w-full text-sm border-gray-200 rounded-lg dark:bg-neutral-800 dark:border-neutral-700 dark:text-white"
                        placeholder="https://example.com/page1&#10;https://example.com/page2"></textarea>
                </div>
            @else
                <p class="text-sm text-yellow-600 dark:text-yellow-400">
                    <x-lucide-alert-triangle class="size-4 inline -mt-0.5" />
                    Ini akan menghapus semua cache untuk <strong>{{ $purgeZoneName }}</strong>. Proses ini tidak bisa dibatalkan.
                </p>
            @endif
        </div>

        <x-slot:footer>
            <button wire:click="$set('showPurge', false)" class="py-2 px-4 text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-800 hover:bg-gray-50 dark:bg-neutral-800 dark:border-neutral-700 dark:text-white">Batal</button>
            <x-nawasara-ui::button wire:click="doPurge" color="danger">Purge Cache</x-nawasara-ui::button>
        </x-slot:footer>
    </x-nawasara-ui::modal>
</div>
