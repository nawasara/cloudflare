<div>
    @php $currentZoneName = collect($this->zones)->firstWhere('id', $zone)['name'] ?? null; @endphp
    <x-nawasara-ui::filter-bar searchPlaceholder="Cari nama record..." searchModel="search">
        <x-nawasara-ui::filter-dropdown
            :label="$currentZoneName ? 'Zone: ' . $currentZoneName : 'Zone'"
            model="zone" :items="$this->zoneOptions" />

        <x-nawasara-ui::filter-dropdown label="Type" model="typeFilter"
            :items="['all' => 'Semua Type', 'A' => 'A', 'AAAA' => 'AAAA', 'CNAME' => 'CNAME', 'MX' => 'MX', 'TXT' => 'TXT', 'NS' => 'NS', 'SRV' => 'SRV']" />

        <x-slot:actions>
            @if ($zone)
                <x-nawasara-ui::button color="primary" variant="flat" size="sm"
                    wire:click="syncRegistry"
                    wire:confirm="Sinkronkan semua DNS record zone ini ke Registry aset?"
                    permission="cloudflare.dns.view">
                    <x-slot:icon>
                        <x-lucide-link wire:loading.class="animate-spin" wire:target="syncRegistry" />
                    </x-slot:icon>
                    Sync ke Registry
                </x-nawasara-ui::button>
            @endif
        </x-slot:actions>

        <x-slot:chips>
            @if ($typeFilter)
                <x-nawasara-ui::filter-chip label="Type: {{ $typeFilter }}" model="typeFilter" />
            @endif
            @if ($search)
                <x-nawasara-ui::filter-chip label="Cari: {{ $search }}" model="search" />
            @endif
        </x-slot:chips>
    </x-nawasara-ui::filter-bar>

    @if ($zone)
        <x-nawasara-ui::table :headers="['Type', 'Name', 'Content', 'OPD / PIC', 'Proxied', 'TTL', '']"
            :title="'DNS Records (' . count($this->records['result'] ?? []) . ' records)'">
            <x-slot:table>
                @forelse ($this->records['result'] ?? [] as $record)
                    @php $asset = $this->assetMap[$record['id']] ?? null; @endphp
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            @php
                                $typeBadge = match($record['type']) {
                                    'A' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
                                    'AAAA' => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-300',
                                    'CNAME' => 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300',
                                    'MX' => 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-300',
                                    'TXT' => 'bg-gray-100 text-gray-800 dark:bg-neutral-700 dark:text-neutral-300',
                                    'NS' => 'bg-teal-100 text-teal-800 dark:bg-teal-900 dark:text-teal-300',
                                    'SRV' => 'bg-pink-100 text-pink-800 dark:bg-pink-900 dark:text-pink-300',
                                    default => 'bg-gray-100 text-gray-800 dark:bg-neutral-700 dark:text-neutral-300',
                                };
                            @endphp
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-mono font-bold {{ $typeBadge }}">
                                {{ $record['type'] }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm font-medium text-gray-800 dark:text-neutral-200 max-w-xs truncate">
                            {{ $record['name'] }}
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500 dark:text-neutral-400 max-w-xs truncate font-mono">
                            {{ $record['content'] }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            @if (! in_array($record['type'], ['A', 'AAAA', 'CNAME']))
                                <span class="text-gray-300 dark:text-neutral-600">-</span>
                            @elseif ($asset && $asset->opd)
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
                            @if ($record['proxied'] ?? false)
                                <span class="inline-flex items-center gap-1 text-orange-500" title="Proxied (orange cloud)">
                                    <x-lucide-cloud class="size-4 fill-orange-500" /> On
                                </span>
                            @elseif (in_array($record['type'], ['A', 'AAAA', 'CNAME']))
                                <span class="inline-flex items-center gap-1 text-gray-400" title="DNS only">
                                    <x-lucide-cloud-off class="size-4" /> Off
                                </span>
                            @else
                                <span class="text-gray-300 dark:text-neutral-600">-</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-neutral-400">
                            {{ ($record['ttl'] ?? 1) === 1 ? 'Auto' : $record['ttl'].'s' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                            <x-nawasara-ui::dropdown-menu-action :id="$record['id']" :items="[
                                ['type' => 'click', 'label' => 'Edit', 'wire:click' => 'openEdit(\'' . $record['id'] . '\')', 'modal' => 'dns-form', 'icon' => 'lucide-pencil', 'permission' => 'cloudflare.dns.edit'],
                                ['type' => 'click', 'label' => 'Hapus', 'wire:click' => 'deleteRecord(\'' . $record['id'] . '\')', 'icon' => 'lucide-trash-2', 'confirm' => 'Yakin ingin menghapus record ini?', 'permission' => 'cloudflare.dns.delete'],
                            ]" />
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-6 py-8 text-center text-sm text-gray-500 dark:text-neutral-400">
                            Tidak ada DNS record ditemukan.
                        </td>
                    </tr>
                @endforelse
            </x-slot:table>

            <x-slot:footer>
                @php
                    $info = $this->records['result_info'] ?? [];
                    $totalPages = $info['total_pages'] ?? 1;
                    $totalCount = $info['total_count'] ?? 0;
                @endphp
                <div class="flex items-center justify-between px-4 py-3">
                    <div class="text-sm text-gray-500 dark:text-neutral-400">
                        Halaman {{ $page }} dari {{ $totalPages }} ({{ $totalCount }} records)
                    </div>
                    <div class="flex gap-2">
                        <button wire:click="previousPage" @disabled($page <= 1)
                            class="px-3 py-1.5 text-sm border rounded-lg disabled:opacity-50 hover:bg-gray-50 dark:border-neutral-700 dark:hover:bg-neutral-700">
                            Prev
                        </button>
                        <button wire:click="nextPage" @disabled($page >= $totalPages)
                            class="px-3 py-1.5 text-sm border rounded-lg disabled:opacity-50 hover:bg-gray-50 dark:border-neutral-700 dark:hover:bg-neutral-700">
                            Next
                        </button>
                    </div>
                </div>
            </x-slot:footer>
        </x-nawasara-ui::table>
    @else
        <div class="text-center py-12">
            <x-lucide-globe class="size-12 mx-auto text-gray-300 dark:text-neutral-600" />
            <p class="mt-3 text-sm text-gray-500 dark:text-neutral-400">Pilih zone terlebih dahulu untuk melihat DNS records.</p>
        </div>
    @endif

    {{-- Create/Edit Modal --}}
    <x-nawasara-ui::modal id="dns-form" maxWidth="lg" :title="$editingId ? 'Edit DNS Record' : 'Tambah DNS Record'">
        <form wire:submit="save" id="cf-dns-form" class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <x-nawasara-ui::form.select label="Type" wire:model.live="formType" name="formType"
                    :placeholder="false" :disabled="(bool) $editingId">
                    @foreach (['A', 'AAAA', 'CNAME', 'MX', 'TXT', 'NS', 'SRV'] as $type)
                        <option value="{{ $type }}">{{ $type }}</option>
                    @endforeach
                </x-nawasara-ui::form.select>

                <x-nawasara-ui::form.select label="TTL" wire:model="formTtl" name="formTtl" :placeholder="false">
                    <option value="1">Auto</option>
                    <option value="60">1 min</option>
                    <option value="300">5 min</option>
                    <option value="600">10 min</option>
                    <option value="1800">30 min</option>
                    <option value="3600">1 hour</option>
                    <option value="86400">1 day</option>
                </x-nawasara-ui::form.select>
            </div>

            <x-nawasara-ui::form.input label="Name" wire:model="formName" placeholder="subdomain atau @ untuk root" useError errorVariable="formName" />

            <x-nawasara-ui::form.input label="Content" wire:model="formContent" :placeholder="match($formType) {
                'A' => '192.168.1.1',
                'AAAA' => '2001:db8::1',
                'CNAME' => 'target.example.com',
                'MX' => 'mail.example.com',
                'TXT' => 'v=spf1 include:_spf.example.com ~all',
                default => 'value',
            }" useError errorVariable="formContent" />

            @if ($formType === 'MX')
                <x-nawasara-ui::form.input label="Priority" type="number" wire:model="formPriority" placeholder="10" />
            @endif

            @if (in_array($formType, ['A', 'AAAA', 'CNAME']))
                <div class="flex items-center gap-3">
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" wire:model="formProxied" class="sr-only peer">
                        <div class="w-9 h-5 bg-gray-200 peer-focus:outline-none rounded-full peer dark:bg-neutral-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all dark:border-neutral-600 peer-checked:bg-orange-500"></div>
                    </label>
                    <span class="text-sm text-gray-700 dark:text-neutral-300">Proxied (orange cloud)</span>
                </div>

                {{-- OPD / PIC Linking --}}
                <div class="pt-4 border-t border-gray-200 dark:border-neutral-700">
                    <h4 class="text-sm font-semibold text-gray-700 dark:text-neutral-300 mb-3">
                        Kepemilikan (Registry)
                    </h4>
                    <p class="text-xs text-gray-500 dark:text-neutral-400 mb-3">
                        Default mengikuti OPD/PIC zone induk. Ubah kalau record ini milik OPD berbeda.
                    </p>

                    <div class="grid grid-cols-1 gap-4">
                        <x-nawasara-ui::form.select label="OPD (opsional)"
                            wire:model.live="formOpdId" placeholder="-- Pilih OPD --">
                            @foreach ($this->opdList as $opd)
                                <option value="{{ $opd->id }}">{{ $opd->code }} - {{ $opd->name }}</option>
                            @endforeach
                        </x-nawasara-ui::form.select>

                        @if ($formOpdId)
                            <x-nawasara-ui::form.select label="PIC (opsional)"
                                wire:model="formPicId" placeholder="-- Pilih PIC --">
                                @foreach ($this->picList as $pic)
                                    <option value="{{ $pic->id }}">{{ $pic->name }}{{ $pic->position ? ' ('.$pic->position.')' : '' }}</option>
                                @endforeach
                            </x-nawasara-ui::form.select>
                        @endif
                    </div>
                </div>
            @endif

        </form>

        <x-slot:footer>
            <x-nawasara-ui::button color="neutral" variant="outline" @click="$dispatch('close-modal', 'dns-form')">Batal</x-nawasara-ui::button>
            <x-nawasara-ui::button type="submit" form="cf-dns-form" color="primary">{{ $editingId ? 'Update' : 'Simpan' }}</x-nawasara-ui::button>
        </x-slot:footer>
    </x-nawasara-ui::modal>
</div>
