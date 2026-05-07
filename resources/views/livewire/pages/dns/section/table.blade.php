<div>
    @php $currentZoneName = $this->zoneOptions[$zone] ?? null; @endphp

    <x-nawasara-ui::sync-info-bar :lastSyncedAt="$this->lastSyncedAt" />

    @php
        $typeOptions = ['A' => 'A', 'AAAA' => 'AAAA', 'CNAME' => 'CNAME', 'MX' => 'MX', 'TXT' => 'TXT', 'NS' => 'NS', 'SRV' => 'SRV'];
        $sortOptions = ['newest' => 'Terbaru dibuat', 'oldest' => 'Terlama dibuat', 'modified' => 'Baru dimodifikasi', 'name' => 'Nama (A-Z)'];
    @endphp

    {{-- Toolbar — single-row layout that stays stable from mobile to desktop.

         Mobile (<sm): everything stacks vertically, full width.
         Tablet+ (sm): one row with filters left, search expands center,
                       actions stick right.

         filter-panel teleports its chips into [data-filter-chips] below the
         toolbar so chip wrapping doesn't disturb the toolbar grid. The
         search chip joins them in the same row for visual consistency. --}}
    <div class="space-y-2 mb-4">
        <div class="flex flex-col md:flex-row md:flex-nowrap md:items-center gap-2">
            {{-- Filter zone (always together, never wraps internally) --}}
            <div class="flex flex-wrap items-center gap-2 shrink-0">
                <x-nawasara-ui::filter-dropdown
                    :label="$currentZoneName ? 'Zone: '.$currentZoneName : 'Zone'"
                    model="zone" :items="$this->zoneOptions" />

                <x-nawasara-ui::filter-panel
                    label="Filter"
                    :state="['typeFilter' => $typeFilter, 'sort' => $sort]"
                    :multiple="['typeFilter']"
                    :labels="['typeFilter' => $typeOptions, 'sort' => $sortOptions]">
                    <x-nawasara-ui::filter-group label="Type" model="typeFilter" :items="$typeOptions" icon="lucide-tag" />
                    <x-nawasara-ui::filter-group label="Urutkan" model="sort" :items="$sortOptions" icon="lucide-arrow-up-down" />
                </x-nawasara-ui::filter-panel>
            </div>

            {{-- Search zone — fills available space between filters and actions. --}}
            <x-nawasara-ui::search-input model="search" placeholder="Cari nama record..." />

            {{-- Action zone — styled to match filter-dropdown idle state
                 (border-gray-200 bg-white) so the toolbar reads as one unit.
                 Inline buttons instead of <x-button> because we need precise
                 visual parity with the dropdown buttons next door. --}}
            @if ($zone)
                <div class="flex items-center gap-2 shrink-0">
                    <x-nawasara-ui::icon-button icon="refresh-cw" tooltip="Sync DNS dari Cloudflare" wire:click="refreshRecords" loadingTarget="refreshRecords" />

                    @can('cloudflare.dns.view')
                        <x-nawasara-ui::icon-button icon="link" tooltip="Sync ke Registry aset" color="emerald" wire:click="syncRegistry" loadingTarget="syncRegistry" wire:confirm="Sinkronkan semua DNS record zone ini ke Registry aset?" />
                    @endcan

                    {{-- Export full dataset of the active zone (xlsx/csv/json).
                         Backed by HasExport trait + GenericArrayExport. --}}
                    <x-nawasara-ui::export-button
                        action="export"
                        tooltip="Ekspor DNS records"
                        permission="cloudflare.dns.view" />
                </div>
            @endif
        </div>

        {{-- Chips row — filter-panel teleports its chips here. The search chip
             below is rendered by Livewire and lives alongside them.

             wire:ignore is CRITICAL: Alpine's x-teleport drops the chips DOM
             *inside* this div, but the server template renders it empty.
             Without wire:ignore, Livewire's morph after $wire.$commit() (which
             fires when filter is applied) removes the teleported children
             because they don't exist in the expected output → chips and badge
             count disappear immediately after filtering.

             The chips DOM is fully Alpine-managed; server never touches it. --}}
        <div wire:ignore data-filter-chips></div>

        @if ($search)
            <div class="flex flex-wrap items-center gap-2">
                <x-nawasara-ui::filter-chip label="Cari: {{ $search }}" model="search" />
            </div>
        @endif
    </div>

    @if ($zone)
        @php
            $pageRecordIds = $this->records->pluck('id')->map(fn ($id) => (string) $id)->values()->all();
        @endphp

        {{-- Selection state lives in Alpine for zero-roundtrip checkbox toggling.
             Each toggle updates selectedIds locally; sync to Livewire happens
             only when a bulk action fires (Alpine pushes the latest array
             to $wire.selected before invoking the server method).

             pageRecordIds is the canonical id list for THIS page (used by
             select-all). All comparisons stringify because Alpine x-model on
             checkbox value="N" stores strings and the Livewire selected[]
             also stringifies on the wire. --}}
        <div x-data="{
                selectedIds: @js(array_map('strval', $selected)),
                pageIds: @js($pageRecordIds),
                get allChecked() {
                    return this.pageIds.length > 0 &&
                        this.pageIds.every(id => this.selectedIds.includes(id));
                },
                set allChecked(v) {
                    if (v) {
                        const merged = new Set([...this.selectedIds, ...this.pageIds]);
                        this.selectedIds = [...merged];
                    } else {
                        this.selectedIds = this.selectedIds.filter(id => ! this.pageIds.includes(id));
                    }
                },
                isSelected(id) { return this.selectedIds.includes(String(id)); },
                clear() { this.selectedIds = []; },
                /* Push current selection to Livewire and invoke the action.
                   Used by bulk delete so the server gets the up-to-date list
                   despite individual toggles never having round-tripped. */
                runBulk(action, confirm) {
                    if (this.selectedIds.length === 0) return;
                    if (confirm && ! window.confirm(confirm)) return;
                    $wire.set('selected', this.selectedIds, false);
                    $wire.call(action);
                    this.clear();
                },
            }">

            @can('cloudflare.dns.delete')
                <x-nawasara-ui::bulk-action-bar
                    xCount="selectedIds.length"
                    xClear="clear()"
                    label="record dipilih">
                    {{-- Bulk delete uses runBulk() to sync selectedIds → Livewire
                         then call the server action in one batched request. --}}
                    <button type="button"
                        x-on:click="runBulk('bulkDelete', `HAPUS ${selectedIds.length} DNS record?`)"
                        class="inline-flex items-center justify-center gap-2 select-none font-medium transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 disabled:opacity-50 disabled:pointer-events-none rounded-md dark:focus-visible:ring-offset-gray-900 h-9 px-3 text-sm border border-rose-600 text-rose-700 hover:bg-rose-50 dark:border-rose-400 dark:text-rose-300 dark:hover:bg-rose-950">
                        <x-lucide-trash-2 class="size-4" />
                        <span>Delete</span>
                    </button>
                </x-nawasara-ui::bulk-action-bar>
            @endcan

            @php
                $selectAllHeader = '<input type="checkbox" x-model="allChecked" class="size-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500 dark:bg-neutral-800 dark:border-neutral-600">';
            @endphp
            <x-nawasara-ui::table
                stickyLast
                :headers="[$selectAllHeader, 'Type', 'Name', 'Content', 'OPD / PIC', 'Proxied', 'TTL', 'Created', 'Sync', '']"
                :title="'DNS Records ('.$this->records->total().' records)'">
                <x-slot:table>
                    @forelse ($this->records as $record)
                        @php $asset = $this->assetMap[$record->record_id] ?? null; @endphp
                        {{-- Selection highlight is bound to Alpine state, not the
                             server-side $selected array. x-bind:class flips the
                             emerald bg + sticky cell override the moment the user
                             toggles the checkbox, no round-trip needed. --}}
                        <tr wire:key="dns-{{ $record->id }}"
                            x-bind:class="isSelected({{ $record->id }})
                                ? 'bg-emerald-50/60 dark:bg-emerald-900/15 [&>td:last-child]:!bg-emerald-50/60 dark:[&>td:last-child]:!bg-emerald-900/15'
                                : ''">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <input type="checkbox" x-model="selectedIds" value="{{ $record->id }}"
                                    class="size-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500 dark:bg-neutral-800 dark:border-neutral-600">
                            </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            @php
                                $typeBadge = match($record->type) {
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
                                {{ $record->type }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm font-medium text-gray-800 dark:text-neutral-200 max-w-xs truncate">
                            {{ $record->name }}
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500 dark:text-neutral-400 max-w-xs truncate font-mono">
                            {{ $record->content }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            @if (! in_array($record->type, ['A', 'AAAA', 'CNAME']))
                                <span class="text-gray-300 dark:text-neutral-600">-</span>
                            @elseif ($asset && $asset->opd)
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
                            @if ($record->proxied)
                                <span class="inline-flex items-center gap-1 text-orange-500" title="Proxied">
                                    <x-lucide-cloud class="size-4 fill-orange-500" /> On
                                </span>
                            @elseif (in_array($record->type, ['A', 'AAAA', 'CNAME']))
                                <span class="inline-flex items-center gap-1 text-gray-400" title="DNS only">
                                    <x-lucide-cloud-off class="size-4" /> Off
                                </span>
                            @else
                                <span class="text-gray-300 dark:text-neutral-600">-</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-neutral-400">
                            {{ $record->ttl === 1 ? 'Auto' : $record->ttl.'s' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            @if ($record->cf_created_at)
                                @php $isNew = $record->cf_created_at->gt(now()->subDay()); @endphp
                                <div class="flex items-center gap-1.5">
                                    <span class="text-gray-700 dark:text-neutral-300" title="Source: Cloudflare. Dibuat {{ $record->cf_created_at->format('d M Y H:i') }}">
                                        {{ $record->cf_created_at->diffForHumans(['short' => true]) }}
                                    </span>
                                    @if ($isNew)
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400 uppercase">
                                            New
                                        </span>
                                    @endif
                                </div>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            <x-nawasara-sync::sync-badge :status="$record->sync_status" :error="$record->sync_error" />
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                            <x-nawasara-ui::dropdown-menu-action :id="$record->id" :items="[
                                ['type' => 'click', 'label' => 'Edit', 'wire:click' => 'openEdit('.$record->id.')', 'modal' => 'dns-form', 'icon' => 'lucide-pencil', 'permission' => 'cloudflare.dns.edit'],
                                ['type' => 'click', 'label' => 'Hapus', 'wire:click' => 'deleteRecord('.$record->id.')', 'icon' => 'lucide-trash-2', 'confirm' => 'Yakin ingin menghapus record ini?', 'permission' => 'cloudflare.dns.delete'],
                            ]" />
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10">
                            @if ($this->lastSyncedAt === null)
                                <x-nawasara-ui::empty-state
                                    icon="lucide-list"
                                    title="Belum ada DNS record"
                                    description="Klik tombol Sync Sekarang untuk fetch record dari Cloudflare."
                                    inline />
                            @else
                                <x-nawasara-ui::empty-state
                                    icon="lucide-search-x"
                                    title="Tidak ada record yang cocok"
                                    description="Coba ubah filter atau hapus search keyword."
                                    variant="filter"
                                    inline />
                            @endif
                        </td>
                    </tr>
                @endforelse
            </x-slot:table>

            <x-slot:footer>
                {{ $this->records->links() }}
            </x-slot:footer>
        </x-nawasara-ui::table>
        </div>{{-- /x-data selection wrapper --}}
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
            @endif

            {{-- Comment + Tags (semua type) --}}
            <div>
                <x-nawasara-ui::form.label value="Comment (opsional)" />
                <x-nawasara-ui::form.textarea wire:model="formComment" rows="2"
                    placeholder="Catatan internal — siapa yang punya, kapan dibuat, kenapa, dll." />
            </div>
            <div>
                <x-nawasara-ui::form.label value="Tags (opsional)" />
                <x-nawasara-ui::form.input wire:model="formTagsInput"
                    placeholder="contoh: kominfo, production, monitoring" />
                <p class="text-xs text-gray-500 dark:text-neutral-400 mt-1">
                    Pisahkan dengan koma. Sama dengan field Tags di Cloudflare dashboard.
                </p>
            </div>

            @if (in_array($formType, ['A', 'AAAA', 'CNAME']))
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
