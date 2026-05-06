<div>
    @php $currentZoneName = collect($this->zones)->firstWhere('id', $zone)['name'] ?? null; @endphp
    <x-nawasara-ui::filter-bar>
        <x-nawasara-ui::filter-dropdown
            :label="$currentZoneName ? 'Zone: ' . $currentZoneName : 'Zone'"
            model="zone" :items="$this->zoneOptions" />
    </x-nawasara-ui::filter-bar>

    @if ($zone)
        <x-nawasara-ui::table :headers="['Deskripsi', 'Expression', 'Action', 'Status', '']"
            :title="'Firewall Rules (' . count($this->rules) . ' rules)'">
            <x-slot:table>
                @forelse ($this->rules as $rule)
                    <tr>
                        <td class="px-6 py-4 text-sm text-gray-800 dark:text-neutral-200 max-w-xs truncate">
                            {{ $rule['description'] ?? '-' }}
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500 dark:text-neutral-400 max-w-sm">
                            <code class="text-xs bg-gray-100 dark:bg-neutral-700 px-2 py-1 rounded break-all">
                                {{ $rule['filter']['expression'] ?? '-' }}
                            </code>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            @php
                                $actionBadge = match($rule['action'] ?? '') {
                                    'block' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
                                    'challenge', 'js_challenge', 'managed_challenge' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
                                    'allow' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
                                    'log' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
                                    default => 'bg-gray-100 text-gray-800 dark:bg-neutral-700 dark:text-neutral-300',
                                };
                            @endphp
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $actionBadge }}">
                                {{ ucfirst(str_replace('_', ' ', $rule['action'] ?? 'unknown')) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            @if ($rule['paused'] ?? false)
                                <span class="text-gray-400">Paused</span>
                            @else
                                <span class="text-green-600 dark:text-green-400">Active</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                            <x-nawasara-ui::dropdown-menu-action :id="$rule['id']" :items="[
                                ['type' => 'click', 'label' => 'Edit', 'wire:click' => 'openEdit(\'' . $rule['id'] . '\')', 'modal' => 'firewall-form', 'icon' => 'lucide-pencil', 'permission' => 'cloudflare.waf.edit'],
                                ['type' => 'click', 'label' => 'Hapus', 'wire:click' => 'deleteRule(\'' . $rule['id'] . '\')', 'icon' => 'lucide-trash-2', 'confirm' => 'Yakin ingin menghapus rule ini?', 'permission' => 'cloudflare.waf.delete'],
                            ]" />
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">
                            <x-nawasara-ui::empty-state
                                icon="lucide-shield"
                                title="Belum ada firewall rule"
                                description="Buat rule via Cloudflare console untuk block traffic mencurigakan, lalu sync ulang."
                                inline />
                        </td>
                    </tr>
                @endforelse
            </x-slot:table>
        </x-nawasara-ui::table>
    @else
        <div class="text-center py-12">
            <x-lucide-shield class="size-12 mx-auto text-gray-300 dark:text-neutral-600" />
            <p class="mt-3 text-sm text-gray-500 dark:text-neutral-400">Pilih zone terlebih dahulu untuk melihat firewall rules.</p>
        </div>
    @endif

    {{-- Create/Edit Modal --}}
    <x-nawasara-ui::modal id="firewall-form" maxWidth="lg" :title="$editingId ? 'Edit Firewall Rule' : 'Tambah Firewall Rule'">
        <form wire:submit="save" id="cf-firewall-form" class="space-y-4">
            <x-nawasara-ui::form.input label="Deskripsi" wire:model="formDescription"
                placeholder="Block bad bots" />

            <x-nawasara-ui::form.textarea label="Expression (Cloudflare filter)"
                wire:model="formExpression" name="formExpression" rows="3"
                class="font-mono"
                placeholder='(ip.src eq 1.2.3.4) or (http.user_agent contains "BadBot")' />

            <div class="grid grid-cols-2 gap-4 items-end">
                <x-nawasara-ui::form.select label="Action" wire:model="formAction" name="formAction" :placeholder="false">
                    <option value="block">Block</option>
                    <option value="challenge">Challenge (CAPTCHA)</option>
                    <option value="js_challenge">JS Challenge</option>
                    <option value="managed_challenge">Managed Challenge</option>
                    <option value="allow">Allow</option>
                    <option value="log">Log</option>
                </x-nawasara-ui::form.select>

                <div class="pb-3">
                    <x-nawasara-ui::form.checkbox label="Paused (nonaktif)" wire:model="formPaused" />
                </div>
            </div>
        </form>

        <x-slot:footer>
            <x-nawasara-ui::button color="neutral" variant="outline" @click="$dispatch('close-modal', 'firewall-form')">Batal</x-nawasara-ui::button>
            <x-nawasara-ui::button type="submit" form="cf-firewall-form" color="primary">{{ $editingId ? 'Update' : 'Simpan' }}</x-nawasara-ui::button>
        </x-slot:footer>
    </x-nawasara-ui::modal>
</div>
