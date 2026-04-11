<div>
    <x-nawasara-ui::filter-bar searchPlaceholder="">
        {{-- Zone Selector --}}
        <select wire:model.live="zone"
            class="py-2 px-3 text-sm border-gray-200 rounded-lg dark:bg-neutral-800 dark:border-neutral-700 dark:text-white">
            <option value="">-- Pilih Zone --</option>
            @foreach ($this->zones as $z)
                <option value="{{ $z['id'] }}">{{ $z['name'] }}</option>
            @endforeach
        </select>
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
                                ['type' => 'click', 'label' => 'Edit', 'wire:click' => 'openEdit(\'' . $rule['id'] . '\')', 'icon' => 'lucide-pencil'],
                                ['type' => 'click', 'label' => 'Hapus', 'wire:click' => 'deleteRule(\'' . $rule['id'] . '\')', 'icon' => 'lucide-trash-2', 'confirm' => 'Yakin ingin menghapus rule ini?'],
                            ]" />
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-8 text-center text-sm text-gray-500 dark:text-neutral-400">
                            Tidak ada firewall rule.
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
    <x-nawasara-ui::modal wire:model="showForm" maxWidth="lg" :title="$editingId ? 'Edit Firewall Rule' : 'Tambah Firewall Rule'">
        <form wire:submit="save" id="cf-firewall-form" class="space-y-4">
            <x-nawasara-ui::form.input label="Deskripsi" wire:model="formDescription" placeholder="Block bad bots" />

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-neutral-300 mb-1">Expression (Cloudflare filter)</label>
                <textarea wire:model="formExpression" rows="3"
                    class="w-full text-sm font-mono border-gray-200 rounded-lg dark:bg-neutral-800 dark:border-neutral-700 dark:text-white"
                    placeholder='(ip.src eq 1.2.3.4) or (http.user_agent contains "BadBot")'></textarea>
                @error('formExpression') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-neutral-300 mb-1">Action</label>
                    <select wire:model="formAction"
                        class="w-full py-2 px-3 text-sm border-gray-200 rounded-lg dark:bg-neutral-800 dark:border-neutral-700 dark:text-white">
                        <option value="block">Block</option>
                        <option value="challenge">Challenge (CAPTCHA)</option>
                        <option value="js_challenge">JS Challenge</option>
                        <option value="managed_challenge">Managed Challenge</option>
                        <option value="allow">Allow</option>
                        <option value="log">Log</option>
                    </select>
                </div>

                <div class="flex items-end pb-1">
                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-neutral-300">
                        <input type="checkbox" wire:model="formPaused" class="rounded border-gray-300 text-blue-600 dark:bg-neutral-800 dark:border-neutral-700">
                        Paused (nonaktif)
                    </label>
                </div>
            </div>

        </form>

        <x-slot:footer>
            <button type="button" wire:click="$set('showForm', false)" class="py-2.5 px-4 text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-800 hover:bg-gray-50 dark:bg-neutral-800 dark:border-neutral-700 dark:text-white">Batal</button>
            <x-nawasara-ui::button type="submit" form="cf-firewall-form" color="primary">{{ $editingId ? 'Update' : 'Simpan' }}</x-nawasara-ui::button>
        </x-slot:footer>
        </form>
    </x-nawasara-ui::modal>
</div>
