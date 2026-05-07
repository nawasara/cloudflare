<div>
    @php $logs = $this->logs; @endphp

    {{-- Filter Bar --}}
    <div class="flex flex-wrap items-end gap-3 mb-6 p-4 rounded-xl border border-gray-200 bg-white dark:bg-neutral-800 dark:border-neutral-700">
        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium text-gray-700 dark:text-neutral-300">Dari</label>
            <input type="date" wire:model="since"
                class="py-2 px-3 text-sm border border-gray-200 rounded-lg dark:bg-neutral-900 dark:border-neutral-700 dark:text-white" />
        </div>
        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium text-gray-700 dark:text-neutral-300">Sampai</label>
            <input type="date" wire:model="before"
                class="py-2 px-3 text-sm border border-gray-200 rounded-lg dark:bg-neutral-900 dark:border-neutral-700 dark:text-white" />
        </div>
        <div class="flex flex-col gap-1 flex-1 min-w-48">
            <label class="text-xs font-medium text-gray-700 dark:text-neutral-300">Actor Email</label>
            <input type="text" wire:model="actor" placeholder="opsional"
                class="py-2 px-3 text-sm border border-gray-200 rounded-lg dark:bg-neutral-900 dark:border-neutral-700 dark:text-white" />
        </div>
        <x-nawasara-ui::button color="primary" size="sm" wire:click="applyFilters">
            <x-slot:icon>
                <x-lucide-filter wire:loading.class="animate-spin" wire:target="applyFilters" />
            </x-slot:icon>
            Apply
        </x-nawasara-ui::button>
    </div>

    @if (! empty($logs['error']))
        <div class="mb-6 p-4 rounded-xl border border-amber-200 bg-amber-50 dark:bg-amber-900/20 dark:border-amber-800">
            <div class="flex gap-3">
                <x-lucide-triangle-alert class="size-5 flex-shrink-0 text-amber-600 dark:text-amber-400 mt-0.5" />
                <div class="text-sm space-y-2">
                    <p class="font-semibold text-amber-800 dark:text-amber-300">Audit log tidak bisa diakses via API Token</p>
                    <p class="text-amber-700 dark:text-amber-400 break-words">{{ $logs['error'] }}</p>
                    <div class="text-xs text-amber-700 dark:text-amber-400 space-y-1">
                        <p class="font-semibold">Cara mengaktifkan:</p>
                        <p>1. Tambahkan ke Vault credential <code class="font-mono">cloudflare</code>:</p>
                        <ul class="list-disc list-inside ml-4 space-y-0.5">
                            <li><code class="font-mono">email</code> = email akun Cloudflare</li>
                            <li><code class="font-mono">global_api_key</code> = Global API Key (My Profile → API Tokens → Global API Key → View)</li>
                        </ul>
                        <p class="mt-2">2. Atau buka langsung di
                            <a href="https://dash.cloudflare.com/{{ \Nawasara\Vault\Facades\Vault::get('cloudflare', 'account_id') }}/audit-log"
                                target="_blank" rel="noopener"
                                class="font-medium underline hover:text-amber-900 dark:hover:text-amber-200">
                                Cloudflare Dashboard <x-lucide-external-link class="size-3 inline -mt-0.5" />
                            </a>
                        </p>
                        <p class="mt-1 italic">
                            Catatan: Permission "Access: Audit Logs" yang ada di API Token UI itu untuk Cloudflare Access (Zero Trust),
                            bukan account audit log umum — endpoint dan tujuan berbeda.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @php $totalCount = $logs['result_info']['total_count'] ?? 0; @endphp

    <x-nawasara-ui::table :headers="['Waktu', 'Actor', 'Action', 'Resource', 'Result', 'Interface']"
        :title="'Cloudflare Audit Log (' . number_format($totalCount) . ' entries)'">
        <x-slot:table>
            @forelse ($logs['data'] as $log)
                @php
                    $actor = $log['actor'] ?? [];
                    $action = $log['action'] ?? [];
                    $resource = $log['resource'] ?? [];
                    $result = $action['result'] ?? null;
                    $when = $log['when'] ?? null;
                @endphp
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-500 dark:text-neutral-400">
                        @if ($when)
                            <div>{{ \Carbon\Carbon::parse($when)->format('d M Y') }}</div>
                            <div class="font-mono">{{ \Carbon\Carbon::parse($when)->format('H:i:s') }}</div>
                        @else
                            -
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        <div class="text-gray-800 dark:text-neutral-200">{{ $actor['email'] ?? $actor['type'] ?? '-' }}</div>
                        @if (! empty($actor['ip']))
                            <div class="text-xs text-gray-500 dark:text-neutral-500 font-mono">{{ $actor['ip'] }}</div>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-sm">
                        <div class="font-medium text-gray-800 dark:text-neutral-200">{{ $action['type'] ?? '-' }}</div>
                        @if (! empty($action['info']))
                            <div class="text-xs text-gray-500 dark:text-neutral-500 max-w-md truncate" title="{{ $action['info'] }}">{{ $action['info'] }}</div>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-500 dark:text-neutral-400">
                        @if (! empty($resource['type']))
                            <div class="font-mono text-xs">{{ $resource['type'] }}</div>
                            @if (! empty($resource['id']))
                                <div class="font-mono text-xs text-gray-400">{{ \Illuminate\Support\Str::limit($resource['id'], 16) }}</div>
                            @endif
                        @else
                            -
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        @if ($result === true)
                            <x-nawasara-ui::badge color="success">Success</x-nawasara-ui::badge>
                        @elseif ($result === false)
                            <x-nawasara-ui::badge color="danger">Failed</x-nawasara-ui::badge>
                        @else
                            <span class="text-xs text-gray-400">-</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-500 dark:text-neutral-500">
                        {{ $log['interface'] ?? '-' }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">
                        @if (! empty($logs['error']))
                            <x-nawasara-ui::empty-state
                                icon="lucide-circle-x"
                                title="Tidak bisa menarik audit log"
                                description="Pastikan API token punya permission Audit Logs: Read di Cloudflare console."
                                inline />
                        @else
                            <x-nawasara-ui::empty-state
                                icon="lucide-scroll-text"
                                title="Tidak ada audit log"
                                description="Tidak ada perubahan tercatat di rentang waktu ini. Coba pilih periode lebih panjang."
                                variant="filter"
                                inline />
                        @endif
                    </td>
                </tr>
            @endforelse
        </x-slot:table>

        @if ($totalCount > 0)
            <x-slot:footer>
                @php
                    $totalPages = $logs['result_info']['total_pages'] ?? 1;
                @endphp
                <div class="flex items-center justify-between px-4 py-3">
                    <div class="text-sm text-gray-500 dark:text-neutral-400">
                        Halaman {{ $page }} dari {{ $totalPages }}
                    </div>
                    <div class="flex gap-2">
                        <x-nawasara-ui::button color="neutral" variant="outline" size="sm"
                            wire:click="previousPage" :disabled="$page <= 1">Prev</x-nawasara-ui::button>
                        <x-nawasara-ui::button color="neutral" variant="outline" size="sm"
                            wire:click="nextPage" :disabled="$page >= $totalPages">Next</x-nawasara-ui::button>
                    </div>
                </div>
            </x-slot:footer>
        @endif
    </x-nawasara-ui::table>
</div>
