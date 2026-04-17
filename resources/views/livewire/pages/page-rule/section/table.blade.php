<div>
    @php
        $currentZoneName = collect($this->zones)->firstWhere('id', $zone)['name'] ?? null;
        $actionTypes = \Nawasara\Cloudflare\Livewire\PageRule\Section\Table::ACTION_TYPES;
        $cacheLevels = \Nawasara\Cloudflare\Livewire\PageRule\Section\Table::CACHE_LEVELS;
        $sslModes = \Nawasara\Cloudflare\Livewire\PageRule\Section\Table::SSL_MODES;
        $forwardingCodes = \Nawasara\Cloudflare\Livewire\PageRule\Section\Table::FORWARDING_STATUS_CODES;
    @endphp

    <x-nawasara-ui::filter-bar>
        <x-nawasara-ui::filter-dropdown
            :label="$currentZoneName ? 'Zone: ' . $currentZoneName : 'Zone'"
            model="zone" :items="$this->zoneOptions" />
    </x-nawasara-ui::filter-bar>

    @if ($zone)
        <x-nawasara-ui::table :headers="['Priority', 'Target', 'Action', 'Status', '']"
            :title="'Page Rules (' . count($this->rules) . ' rules)'">
            <x-slot:table>
                @forelse ($this->rules as $rule)
                    @php
                        $action = $rule['actions'][0] ?? [];
                        $actionType = $action['id'] ?? '';
                        $actionLabel = $actionTypes[$actionType] ?? $actionType;
                        $value = $action['value'] ?? null;
                        $valueText = match (true) {
                            is_array($value) && isset($value['url']) => $value['url'] . ' (' . ($value['status_code'] ?? '?') . ')',
                            is_scalar($value) => (string) $value,
                            default => '',
                        };
                        $isActive = ($rule['status'] ?? 'active') === 'active';
                    @endphp
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-neutral-400 font-mono">
                            {{ $rule['priority'] ?? '-' }}
                        </td>
                        <td class="px-6 py-4 text-sm font-mono text-gray-800 dark:text-neutral-200 max-w-md break-all">
                            {{ $rule['targets'][0]['constraint']['value'] ?? '-' }}
                        </td>
                        <td class="px-6 py-4 text-sm">
                            <div class="font-medium text-gray-800 dark:text-neutral-200">{{ $actionLabel }}</div>
                            @if ($valueText)
                                <div class="text-xs text-gray-500 dark:text-neutral-500 font-mono break-all">{{ $valueText }}</div>
                            @endif
                            @if (count($rule['actions'] ?? []) > 1)
                                <div class="text-xs text-blue-600 dark:text-blue-400 mt-1">
                                    +{{ count($rule['actions']) - 1 }} action lain
                                </div>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            @if ($isActive)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400">Active</span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700 dark:bg-neutral-700 dark:text-neutral-400">Disabled</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                            <x-nawasara-ui::dropdown-menu-action :id="$rule['id']" :items="[
                                ['type' => 'click', 'label' => 'Edit', 'wire:click' => 'openEdit(\'' . $rule['id'] . '\')', 'modal' => 'pagerule-form', 'icon' => 'lucide-pencil', 'permission' => 'cloudflare.pagerule.edit'],
                                ['type' => 'click', 'label' => $isActive ? 'Disable' : 'Enable', 'wire:click' => 'toggleStatus(\'' . $rule['id'] . '\')', 'icon' => $isActive ? 'lucide-pause' : 'lucide-play', 'permission' => 'cloudflare.pagerule.edit'],
                                ['type' => 'click', 'label' => 'Hapus', 'wire:click' => 'deleteRule(\'' . $rule['id'] . '\')', 'icon' => 'lucide-trash-2', 'confirm' => 'Yakin ingin menghapus rule ini?', 'permission' => 'cloudflare.pagerule.delete'],
                            ]" />
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-neutral-400">
                            Belum ada page rule untuk zone ini.
                        </td>
                    </tr>
                @endforelse
            </x-slot:table>
        </x-nawasara-ui::table>
    @endif

    {{-- Create/Edit Modal --}}
    <x-nawasara-ui::modal id="pagerule-form" maxWidth="lg" :title="$editingId ? 'Edit Page Rule' : 'Tambah Page Rule'">
        <form wire:submit="save" id="cf-page-rule-form" class="space-y-4">
            <x-nawasara-ui::form.input label="Target URL Pattern"
                wire:model="formTarget" name="formTarget"
                placeholder="*example.com/admin/*"
                useError errorVariable="formTarget" />
            <p class="text-xs text-gray-500 dark:text-neutral-400 -mt-2">
                Gunakan <code class="font-mono">*</code> sebagai wildcard. Contoh: <code class="font-mono">*ponorogo.go.id/wp-admin/*</code>
            </p>

            <div class="grid grid-cols-2 gap-4">
                <x-nawasara-ui::form.select label="Action" wire:model.live="formActionType" name="formActionType" :placeholder="false">
                    @foreach ($actionTypes as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </x-nawasara-ui::form.select>

                <x-nawasara-ui::form.input label="Priority" type="number"
                    wire:model="formPriority" name="formPriority" :placeholder="false" />
            </div>

            {{-- Action-specific value field --}}
            @if ($formActionType === 'forwarding_url')
                <x-nawasara-ui::form.input label="Destination URL"
                    wire:model="formActionValue" name="formActionValue"
                    placeholder="https://www.example.com/$1" />
                <x-nawasara-ui::form.select label="Status Code" wire:model="formForwardingStatus" name="formForwardingStatus" :placeholder="false">
                    @foreach ($forwardingCodes as $code => $label)
                        <option value="{{ $code }}">{{ $label }}</option>
                    @endforeach
                </x-nawasara-ui::form.select>
            @elseif ($formActionType === 'cache_level')
                <x-nawasara-ui::form.select label="Cache Level" wire:model="formActionValue" name="formActionValue" :placeholder="false">
                    <option value="">-- Pilih --</option>
                    @foreach ($cacheLevels as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </x-nawasara-ui::form.select>
            @elseif ($formActionType === 'ssl')
                <x-nawasara-ui::form.select label="SSL Mode" wire:model="formActionValue" name="formActionValue" :placeholder="false">
                    <option value="">-- Pilih --</option>
                    @foreach ($sslModes as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </x-nawasara-ui::form.select>
            @elseif ($formActionType === 'browser_cache_ttl')
                <x-nawasara-ui::form.input label="TTL (detik)" type="number"
                    wire:model="formActionValue" name="formActionValue"
                    placeholder="14400" />
                <p class="text-xs text-gray-500 dark:text-neutral-400 -mt-2">
                    Contoh: 14400 (4 jam), 86400 (1 hari), 604800 (1 minggu)
                </p>
            @else
                <p class="text-xs text-gray-500 dark:text-neutral-400 italic">
                    Action ini tidak butuh value tambahan.
                </p>
            @endif

            <x-nawasara-ui::form.select label="Status" wire:model="formStatus" name="formStatus" :placeholder="false">
                <option value="active">Active</option>
                <option value="disabled">Disabled</option>
            </x-nawasara-ui::form.select>
        </form>

        <x-slot:footer>
            <x-nawasara-ui::button color="neutral" variant="outline" @click="$dispatch('close-modal', 'pagerule-form')">Batal</x-nawasara-ui::button>
            <x-nawasara-ui::button type="submit" form="cf-page-rule-form" color="primary">{{ $editingId ? 'Update' : 'Simpan' }}</x-nawasara-ui::button>
        </x-slot:footer>
    </x-nawasara-ui::modal>
</div>
