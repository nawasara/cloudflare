<div>
    @php
        $periods = \Nawasara\Cloudflare\Livewire\Analytics\Section\OpdRollup::PERIOD_OPTIONS;
        $currentOpdName = $this->opdList->firstWhere('id', $opdId)?->name;
    @endphp
    <x-nawasara-ui::filter-bar>
        <x-nawasara-ui::filter-dropdown
            :label="$currentOpdName ? 'OPD: ' . $currentOpdName : 'OPD'"
            model="opdId" :items="$this->opdOptions" />
        <x-nawasara-ui::filter-dropdown
            :label="'Periode: ' . ($periods[$period] ?? '24 Jam')"
            model="period" :items="$periods" />
    </x-nawasara-ui::filter-bar>

    @if (! $opdId)
        <div class="text-center py-12">
            <x-lucide-building-2 class="size-12 mx-auto text-gray-300 dark:text-neutral-600" />
            <p class="mt-3 text-sm text-gray-500 dark:text-neutral-400">
                Pilih OPD untuk melihat agregat traffic semua domain milik OPD tersebut.
            </p>
            @if ($this->opdList->isEmpty())
                <p class="mt-2 text-xs text-yellow-600 dark:text-yellow-400">
                    Belum ada OPD yang memiliki domain dari Cloudflare. Lakukan Sync ke Registry di halaman Zones terlebih dahulu.
                </p>
            @endif
        </div>
    @else
        @php $rollup = $this->rollup; @endphp

        @if (! empty($rollup['error']))
            <div class="mb-6 p-4 rounded-xl border border-yellow-200 bg-yellow-50 dark:bg-yellow-900/20 dark:border-yellow-800">
                <div class="flex gap-3">
                    <x-lucide-triangle-alert class="size-5 flex-shrink-0 text-yellow-600 dark:text-yellow-400 mt-0.5" />
                    <p class="text-sm text-yellow-700 dark:text-yellow-300">{{ $rollup['error'] }}</p>
                </div>
            </div>
        @endif

        @if (! empty($rollup['totals']))
            @php
                $totals = $rollup['totals'];
                $requests = $totals['requests'];
                $bandwidth = $totals['bandwidth'];
                $threats = $totals['threats'];
                $uniques = $totals['uniques'];
                $perZone = $rollup['per_zone'] ?? [];
                $domainCount = $this->domains->count();
            @endphp

            <div class="mb-4 text-sm text-gray-600 dark:text-neutral-400">
                Agregat dari <strong>{{ $domainCount }} domain</strong> milik OPD ini.
            </div>

            {{-- Stats Cards --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <div class="bg-white dark:bg-neutral-800 rounded-xl border border-gray-200 dark:border-neutral-700 p-5">
                    <div class="flex items-center gap-3">
                        <div class="flex-shrink-0 size-10 flex items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-900/30">
                            <x-lucide-globe class="size-5 text-blue-600 dark:text-blue-400" />
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-neutral-400">Total Requests</p>
                            <p class="text-2xl font-bold text-gray-800 dark:text-neutral-200">{{ number_format($requests['all']) }}</p>
                        </div>
                    </div>
                    <div class="mt-3 flex gap-3 text-xs">
                        <span class="text-green-600 dark:text-green-400">Cached: {{ number_format($requests['cached']) }}</span>
                        <span class="text-gray-400">|</span>
                        <span class="text-orange-600 dark:text-orange-400">Uncached: {{ number_format($requests['uncached']) }}</span>
                    </div>
                </div>

                <div class="bg-white dark:bg-neutral-800 rounded-xl border border-gray-200 dark:border-neutral-700 p-5">
                    <div class="flex items-center gap-3">
                        <div class="flex-shrink-0 size-10 flex items-center justify-center rounded-lg bg-green-100 dark:bg-green-900/30">
                            <x-lucide-hard-drive class="size-5 text-green-600 dark:text-green-400" />
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-neutral-400">Bandwidth</p>
                            <p class="text-2xl font-bold text-gray-800 dark:text-neutral-200">{{ $this->formatBytes($bandwidth['all']) }}</p>
                        </div>
                    </div>
                    <div class="mt-3 flex gap-3 text-xs">
                        <span class="text-green-600 dark:text-green-400">Cached: {{ $this->formatBytes($bandwidth['cached']) }}</span>
                        <span class="text-gray-400">|</span>
                        <span class="text-orange-600 dark:text-orange-400">Uncached: {{ $this->formatBytes($bandwidth['uncached']) }}</span>
                    </div>
                </div>

                <div class="bg-white dark:bg-neutral-800 rounded-xl border border-gray-200 dark:border-neutral-700 p-5">
                    <div class="flex items-center gap-3">
                        <div class="flex-shrink-0 size-10 flex items-center justify-center rounded-lg bg-red-100 dark:bg-red-900/30">
                            <x-lucide-shield-alert class="size-5 text-red-600 dark:text-red-400" />
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-neutral-400">Threats Blocked</p>
                            <p class="text-2xl font-bold text-gray-800 dark:text-neutral-200">{{ number_format($threats['all']) }}</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-neutral-800 rounded-xl border border-gray-200 dark:border-neutral-700 p-5">
                    <div class="flex items-center gap-3">
                        <div class="flex-shrink-0 size-10 flex items-center justify-center rounded-lg bg-purple-100 dark:bg-purple-900/30">
                            <x-lucide-users class="size-5 text-purple-600 dark:text-purple-400" />
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-neutral-400">Unique Visitors</p>
                            <p class="text-2xl font-bold text-gray-800 dark:text-neutral-200">{{ number_format($uniques['all']) }}</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Per-Domain Breakdown --}}
            <div class="bg-white dark:bg-neutral-800 rounded-xl border border-gray-200 dark:border-neutral-700 overflow-hidden mb-6">
                <div class="px-5 py-4 border-b border-gray-200 dark:border-neutral-700">
                    <h4 class="font-semibold text-gray-700 dark:text-neutral-300">Breakdown per Domain</h4>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-neutral-700/30">
                            <tr class="text-left text-xs uppercase text-gray-500 dark:text-neutral-400">
                                <th class="px-5 py-3">Domain</th>
                                <th class="px-5 py-3">PIC</th>
                                <th class="px-5 py-3 text-right">Requests</th>
                                <th class="px-5 py-3 text-right">Bandwidth</th>
                                <th class="px-5 py-3 text-right">Threats</th>
                                <th class="px-5 py-3 text-right">% Traffic</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-neutral-700">
                            @foreach ($this->domains->sortByDesc(fn ($d) => $perZone[$d->external_id]['requests'] ?? 0) as $domain)
                                @php
                                    $z = $perZone[$domain->external_id] ?? ['requests' => 0, 'bandwidth' => 0, 'threats' => 0, 'uniques' => 0];
                                    $pct = $requests['all'] > 0 ? round(($z['requests'] / $requests['all']) * 100, 1) : 0;
                                @endphp
                                <tr>
                                    <td class="px-5 py-3 font-medium text-gray-800 dark:text-neutral-200">{{ $domain->identifier }}</td>
                                    <td class="px-5 py-3 text-gray-500 dark:text-neutral-400">{{ $domain->pic->name ?? '-' }}</td>
                                    <td class="px-5 py-3 text-right text-gray-800 dark:text-neutral-200 font-mono">{{ number_format($z['requests']) }}</td>
                                    <td class="px-5 py-3 text-right text-gray-800 dark:text-neutral-200 font-mono">{{ $this->formatBytes($z['bandwidth']) }}</td>
                                    <td class="px-5 py-3 text-right text-gray-800 dark:text-neutral-200 font-mono">{{ number_format($z['threats']) }}</td>
                                    <td class="px-5 py-3 text-right">
                                        <div class="flex items-center gap-2 justify-end">
                                            <div class="w-20 bg-gray-100 dark:bg-neutral-700 rounded-full h-2">
                                                <div class="bg-blue-500 h-2 rounded-full" style="width: {{ min($pct, 100) }}%"></div>
                                            </div>
                                            <span class="text-xs text-gray-500 dark:text-neutral-400 w-10 text-right">{{ $pct }}%</span>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Top Countries --}}
            @if (! empty($requests['country']))
                <div class="bg-white dark:bg-neutral-800 rounded-xl border border-gray-200 dark:border-neutral-700 p-5">
                    <h4 class="font-semibold text-gray-700 dark:text-neutral-300 mb-3">Top Countries</h4>
                    <div class="space-y-2">
                        @foreach (collect($requests['country'])->sortByDesc(fn ($v) => $v)->take(10) as $country => $count)
                            @php $pct = $requests['all'] > 0 ? round(($count / $requests['all']) * 100, 1) : 0; @endphp
                            <div class="flex items-center gap-3 text-sm">
                                <span class="w-8 font-mono text-gray-600 dark:text-neutral-400">{{ $country }}</span>
                                <div class="flex-1 bg-gray-100 dark:bg-neutral-700 rounded-full h-2">
                                    <div class="bg-blue-500 h-2 rounded-full" style="width: {{ min($pct, 100) }}%"></div>
                                </div>
                                <span class="w-24 text-right text-gray-500 dark:text-neutral-400">{{ number_format($count) }} ({{ $pct }}%)</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        @endif
    @endif
</div>
