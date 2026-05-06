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
        {{-- Premium empty state — pilih OPD --}}
        <div class="text-center py-16 px-6 border-2 border-dashed border-gray-200 dark:border-neutral-700 rounded-xl bg-gray-50/50 dark:bg-neutral-900/40">
            <div class="inline-flex items-center justify-center size-14 rounded-2xl bg-gray-100 dark:bg-neutral-800 mb-4">
                <x-lucide-building-2 class="size-7 text-gray-400 dark:text-neutral-500" />
            </div>
            <p class="text-base font-semibold text-gray-800 dark:text-neutral-200">
                Pilih OPD untuk melihat agregat traffic
            </p>
            <p class="mt-2 text-sm text-gray-500 dark:text-neutral-400 max-w-sm mx-auto">
                Filter di atas — pilih salah satu OPD dan periode waktu untuk lihat traffic agregat semua domain milik OPD tersebut.
            </p>
            @if ($this->opdList->isEmpty())
                <p class="mt-3 text-xs text-amber-700 dark:text-amber-400 max-w-md mx-auto">
                    <x-lucide-triangle-alert class="size-3 inline -mt-0.5" />
                    Belum ada OPD dengan domain dari Cloudflare. Lakukan Sync ke Registry di halaman Zones terlebih dahulu.
                </p>
            @endif
        </div>
    @else
        @php $rollup = $this->rollup; @endphp

        @if (! empty($rollup['error']))
            <div class="mb-6 p-4 rounded-xl border border-amber-200 bg-amber-50 dark:bg-amber-900/20 dark:border-amber-800">
                <div class="flex gap-3">
                    <x-lucide-triangle-alert class="size-5 flex-shrink-0 text-amber-700 dark:text-amber-400 mt-0.5" />
                    <p class="text-sm text-amber-800 dark:text-amber-300">{{ $rollup['error'] }}</p>
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

            {{-- Stats Cards — pakai design-system stat-card untuk konsistensi
                 dengan analytics overview (sister page) dan dashboard /home.
                 Description sub-label kasih cache hit rate insight. --}}
            @php
                $totalReq = $requests['all'] ?? 0;
                $cacheRate = $totalReq > 0 ? round((($requests['cached'] ?? 0) / $totalReq) * 100, 1) : 0;
                $totalBw = $bandwidth['all'] ?? 0;
                $bwSavedPct = $totalBw > 0 ? round((($bandwidth['cached'] ?? 0) / $totalBw) * 100, 1) : 0;
            @endphp
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <x-nawasara-ui::stat-card
                    label="Total Requests"
                    :value="number_format($totalReq)"
                    icon="lucide-globe"
                    color="primary"
                    :description="$totalReq > 0 ? $cacheRate.'% cached' : null"
                    accent />

                <x-nawasara-ui::stat-card
                    label="Bandwidth"
                    :value="$this->formatBytes($totalBw)"
                    icon="lucide-hard-drive"
                    color="info"
                    :description="$totalBw > 0 ? $bwSavedPct.'% via cache' : null"
                    accent />

                <x-nawasara-ui::stat-card
                    label="Threats Blocked"
                    :value="number_format($threats['all'] ?? 0)"
                    icon="lucide-shield-alert"
                    :color="($threats['all'] ?? 0) > 0 ? 'danger' : 'neutral'"
                    description="oleh Cloudflare WAF"
                    accent />

                <x-nawasara-ui::stat-card
                    label="Unique Visitors"
                    :value="number_format($uniques['all'] ?? 0)"
                    icon="lucide-users"
                    color="success"
                    :description="number_format(($uniques['all'] ?? 0) / max($domainCount, 1), 0).'/domain rata-rata'"
                    accent />
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
                                                <div class="bg-emerald-600 h-2 rounded-full" style="width: {{ min($pct, 100) }}%"></div>
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
                                    <div class="bg-emerald-600 h-2 rounded-full" style="width: {{ min($pct, 100) }}%"></div>
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
