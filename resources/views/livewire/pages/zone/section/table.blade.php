<div>
    <x-nawasara-ui::sync-info-bar
        :lastSyncedAt="$this->lastSyncedAt"
        neverSyncedMessage='Belum pernah di-sync. Klik "Sync Sekarang" untuk fetch dari Cloudflare.' />

    {{-- Toolbar — search + sync buttons + export. No filter dimensions. --}}
    <div class="space-y-2 mb-4">
        <div class="flex flex-col md:flex-row md:flex-nowrap md:items-center gap-2">
            <x-nawasara-ui::search-input model="search" placeholder="Cari domain..." />

            <div class="flex items-center gap-2 shrink-0">
                <x-nawasara-ui::icon-button icon="refresh-cw" tooltip="Sync zones dari Cloudflare" wire:click="refreshZones" loadingTarget="refreshZones" />

                @can('cloudflare.zone.view')
                    <x-nawasara-ui::icon-button icon="link" tooltip="Sync zones ke Registry aset" color="emerald" wire:click="syncRegistry" loadingTarget="syncRegistry" wire:confirm="Sinkronkan daftar zone Cloudflare ke Registry aset?" />
                @endcan

                <x-nawasara-ui::export-button
                    action="export"
                    tooltip="Ekspor zones list"
                    permission="cloudflare.zone.view" />
            </div>
        </div>

        @if ($search)
            <div class="flex flex-wrap items-center gap-2">
                <x-nawasara-ui::filter-chip label="Cari: {{ $search }}" model="search" />
            </div>
        @endif
    </div>

    <x-nawasara-ui::table
        stickyLast
        :headers="['Domain', 'OPD / PIC', 'Status', 'Plan', 'DNS Records', 'Sync', '']"
        :title="'Zones ('.$this->zones->total().' domain)'">
        <x-slot:table>
            @forelse ($this->zones as $zone)
                @php $asset = $this->assetMap[$zone->zone_id] ?? null; @endphp
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-800 dark:text-neutral-200">
                        {{ $zone->name }}
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
                            <x-nawasara-ui::badge color="warning">Belum ditetapkan</x-nawasara-ui::badge>
                        @else
                            <x-nawasara-ui::badge color="neutral">Belum di-link</x-nawasara-ui::badge>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        @php
                            $statusColor = match($zone->status) {
                                'active' => 'success',
                                'pending' => 'warning',
                                'moved', 'deleted' => 'danger',
                                default => 'neutral',
                            };
                        @endphp
                        <x-nawasara-ui::badge :color="$statusColor">
                            {{ ucfirst($zone->status ?? 'unknown') }}
                        </x-nawasara-ui::badge>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-neutral-400">
                        {{ $zone->plan_name ?? '-' }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-neutral-300 font-mono">
                        {{ $zone->dns_records_count }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        <x-nawasara-sync::sync-badge :status="$zone->sync_status" :error="$zone->sync_error" />
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                        <x-nawasara-ui::dropdown-menu-action :id="$zone->id" :items="[
                            ['type' => 'click', 'label' => 'Detail', 'wire:click' => 'openDetail('.$zone->id.')', 'modal' => 'zone-detail', 'icon' => 'lucide-eye', 'permission' => 'cloudflare.zone.view'],
                            ['type' => 'click', 'label' => 'Purge Cache', 'wire:click' => 'openPurge(\'' . $zone->zone_id . '\', \'' . $zone->name . '\')', 'icon' => 'lucide-trash-2', 'permission' => 'cloudflare.cache.purge'],
                            ['type' => 'link', 'label' => 'DNS Records', 'href' => url('nawasara-cloudflare/dns?zone=' . $zone->zone_id), 'icon' => 'lucide-list', 'navigate' => true, 'permission' => 'cloudflare.dns.view'],
                        ]" />
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">
                        @if ($this->lastSyncedAt === null)
                            <x-nawasara-ui::empty-state
                                icon="lucide-globe"
                                title="Database zone masih kosong"
                                description="Klik tombol Sync Sekarang untuk fetch zone dari Cloudflare."
                                inline />
                        @elseif ($search !== '')
                            <x-nawasara-ui::empty-state
                                icon="lucide-search-x"
                                title="Tidak ada zone yang cocok"
                                description="Coba ubah search keyword atau hapus filter."
                                variant="filter"
                                inline />
                        @else
                            <x-nawasara-ui::empty-state
                                icon="lucide-globe"
                                title="Belum ada zone terdaftar"
                                description="Tambah domain di Cloudflare console, lalu sync ulang."
                                inline />
                        @endif
                    </td>
                </tr>
            @endforelse
        </x-slot:table>

        <x-slot:footer>
            {{ $this->zones->links() }}
        </x-slot:footer>
    </x-nawasara-ui::table>

    {{-- Detail Modal --}}
    <x-nawasara-ui::modal id="zone-detail" maxWidth="2xl" :title="$this->detail?->name ?? 'Zone Detail'">
        @if ($this->detail)
            @php $z = $this->detail; @endphp
            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div><span class="text-gray-500 dark:text-neutral-400">Domain:</span> <span class="font-medium text-gray-800 dark:text-neutral-200">{{ $z->name }}</span></div>
                    <div><span class="text-gray-500 dark:text-neutral-400">Status:</span> <span class="font-medium text-gray-800 dark:text-neutral-200">{{ ucfirst($z->status ?? '-') }}</span></div>
                    <div><span class="text-gray-500 dark:text-neutral-400">Plan:</span> <span class="font-medium text-gray-800 dark:text-neutral-200">{{ $z->plan_name ?? '-' }}</span></div>
                    <div><span class="text-gray-500 dark:text-neutral-400">Type:</span> <span class="font-medium text-gray-800 dark:text-neutral-200">{{ ucfirst($z->type ?? '-') }}</span></div>
                    <div><span class="text-gray-500 dark:text-neutral-400">DNS Records:</span> <span class="font-medium font-mono text-gray-800 dark:text-neutral-200">{{ $z->dns_records_count }}</span></div>
                    <div><span class="text-gray-500 dark:text-neutral-400">Created:</span> <span class="text-gray-800 dark:text-neutral-200">{{ $z->cf_created_at?->format('d M Y') ?? '-' }}</span></div>
                </div>

                {{-- SSL Mode --}}
                <div>
                    <h4 class="font-semibold text-gray-700 dark:text-neutral-300 mb-2">SSL/TLS Mode</h4>
                    <x-nawasara-ui::button-group>
                        @foreach (['off', 'flexible', 'full', 'strict'] as $mode)
                            <x-nawasara-ui::button size="sm"
                                color="{{ $detailSsl === $mode ? 'primary' : 'neutral' }}"
                                variant="{{ $detailSsl === $mode ? 'solid' : 'outline' }}"
                                wire:click="setSslMode('{{ $mode }}')">
                                {{ ucfirst($mode) }}
                            </x-nawasara-ui::button>
                        @endforeach
                    </x-nawasara-ui::button-group>
                </div>

                {{-- Security Level / Under Attack Mode --}}
                <div>
                    <h4 class="font-semibold text-gray-700 dark:text-neutral-300 mb-2">Security Level</h4>

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
                        <x-nawasara-ui::toggle
                            :active="$detailSecurityLevel === 'under_attack'"
                            color="danger"
                            wire:click="setSecurityLevel('{{ $detailSecurityLevel === 'under_attack' ? 'medium' : 'under_attack' }}')"
                            wire:confirm="{{ $detailSecurityLevel === 'under_attack' ? 'Nonaktifkan Under Attack Mode?' : 'Aktifkan Under Attack Mode?' }}" />
                    </div>

                    <x-nawasara-ui::button-group>
                        @foreach (['off' => 'Off', 'essentially_off' => 'Essentially Off', 'low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'under_attack' => 'Under Attack'] as $level => $label)
                            @php
                                $isActive = $detailSecurityLevel === $level;
                                $color = $isActive ? ($level === 'under_attack' ? 'danger' : 'primary') : 'neutral';
                                $variant = $isActive ? 'solid' : 'outline';
                            @endphp
                            @if ($level === 'under_attack')
                                <x-nawasara-ui::button size="sm" :color="$color" :variant="$variant"
                                    wire:click="setSecurityLevel('{{ $level }}')"
                                    wire:confirm="Aktifkan Under Attack Mode?">
                                    {{ $label }}
                                </x-nawasara-ui::button>
                            @else
                                <x-nawasara-ui::button size="sm" :color="$color" :variant="$variant"
                                    wire:click="setSecurityLevel('{{ $level }}')">
                                    {{ $label }}
                                </x-nawasara-ui::button>
                            @endif
                        @endforeach
                    </x-nawasara-ui::button-group>
                </div>

                {{-- Name Servers --}}
                @if (! empty($z->name_servers))
                    <div>
                        <h4 class="font-semibold text-gray-700 dark:text-neutral-300 mb-2">Name Servers</h4>
                        <div class="space-y-1">
                            @foreach ($z->name_servers as $ns)
                                <div class="text-sm font-mono text-gray-600 dark:text-neutral-400 bg-gray-50 dark:bg-neutral-700/50 px-3 py-1.5 rounded">{{ $ns }}</div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <x-slot:footer>
                <x-nawasara-ui::button color="neutral" variant="outline" wire:click="closeDetail">Tutup</x-nawasara-ui::button>
            </x-slot:footer>
        @endif
    </x-nawasara-ui::modal>

    {{-- Purge Cache Modal --}}
    <x-nawasara-ui::modal id="zone-purge" maxWidth="md" :title="'Purge Cache: '.$purgeZoneName">
        <div class="space-y-4">
            <div class="flex gap-3">
                <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-neutral-300">
                    <input type="radio" wire:model.live="purgeType" value="all" class="text-emerald-600">
                    Purge Everything
                </label>
                <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-neutral-300">
                    <input type="radio" wire:model.live="purgeType" value="urls" class="text-emerald-600">
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
                <p class="text-sm text-amber-700 dark:text-amber-400">
                    <x-lucide-triangle-alert class="size-4 inline -mt-0.5" />
                    Ini akan menghapus semua cache untuk <strong>{{ $purgeZoneName }}</strong>. Proses ini tidak bisa dibatalkan.
                </p>
            @endif
        </div>

        <x-slot:footer>
            <x-nawasara-ui::button color="neutral" variant="outline" @click="$dispatch('close-modal', 'zone-purge')">Batal</x-nawasara-ui::button>
            <x-nawasara-ui::button color="danger" wire:click="doPurge">Purge Cache</x-nawasara-ui::button>
        </x-slot:footer>
    </x-nawasara-ui::modal>
</div>
