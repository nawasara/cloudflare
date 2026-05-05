<div>
    {{-- Sync info bar --}}
    <div class="mb-3 flex items-center justify-between text-xs text-gray-500 dark:text-neutral-400">
        <div class="flex items-center gap-3">
            @if ($this->lastSyncedAt)
                <span><x-lucide-clock class="size-3 inline" /> Last sync: {{ $this->lastSyncedAt }}</span>
            @else
                <span class="text-yellow-600">Belum pernah di-sync. Klik "Sync Sekarang" untuk fetch dari Cloudflare.</span>
            @endif
        </div>
        <a href="{{ url('admin/sync/jobs') }}" wire:navigate class="text-blue-600 hover:underline">
            Lihat Sync Jobs →
        </a>
    </div>

    <x-nawasara-ui::filter-bar searchPlaceholder="Cari domain..." searchModel="search">
        <x-slot:actions>
            <x-nawasara-ui::button color="neutral" variant="outline" size="sm" wire:click="refreshZones">
                <x-slot:icon>
                    <x-lucide-refresh-cw wire:loading.class="animate-spin" wire:target="refreshZones" />
                </x-slot:icon>
                Sync Sekarang
            </x-nawasara-ui::button>

            <x-nawasara-ui::button color="primary" variant="flat" size="sm"
                wire:click="syncRegistry"
                wire:confirm="Sinkronkan daftar zone Cloudflare ke Registry aset?"
                permission="cloudflare.zone.view">
                <x-slot:icon>
                    <x-lucide-link wire:loading.class="animate-spin" wire:target="syncRegistry" />
                </x-slot:icon>
                Sync ke Registry
            </x-nawasara-ui::button>
        </x-slot:actions>

        <x-slot:chips>
            @if ($search)
                <x-nawasara-ui::filter-chip label="Cari: {{ $search }}" model="search" />
            @endif
        </x-slot:chips>
    </x-nawasara-ui::filter-bar>

    <x-nawasara-ui::table
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
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-50 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400">
                                Belum ditetapkan
                            </span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500 dark:bg-neutral-700 dark:text-neutral-400">
                                Belum di-link
                            </span>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        @php
                            $statusClass = match($zone->status) {
                                'active' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
                                'pending' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
                                'moved', 'deleted' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
                                default => 'bg-gray-100 text-gray-800 dark:bg-neutral-700 dark:text-neutral-300',
                            };
                        @endphp
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusClass }}">
                            {{ ucfirst($zone->status ?? 'unknown') }}
                        </span>
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
                    <td colspan="7" class="px-6 py-8 text-center text-sm text-gray-500 dark:text-neutral-400">
                        @if ($this->lastSyncedAt === null)
                            Database masih kosong. Klik <strong>Sync Sekarang</strong>.
                        @else
                            Tidak ada zone ditemukan.
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
            <x-nawasara-ui::button color="neutral" variant="outline" @click="$dispatch('close-modal', 'zone-purge')">Batal</x-nawasara-ui::button>
            <x-nawasara-ui::button color="danger" wire:click="doPurge">Purge Cache</x-nawasara-ui::button>
        </x-slot:footer>
    </x-nawasara-ui::modal>
</div>
