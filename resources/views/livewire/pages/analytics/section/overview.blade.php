<div>
    @php
        $periods = \Nawasara\Cloudflare\Livewire\Analytics\Section\Overview::PERIOD_OPTIONS;
        $currentZoneName = collect($this->zones)->firstWhere('id', $zone)['name'] ?? null;
    @endphp
    <x-nawasara-ui::filter-bar>
        <x-nawasara-ui::filter-dropdown
            :label="$currentZoneName ? 'Zone: ' . $currentZoneName : 'Zone'"
            model="zone" :items="$this->zoneOptions" />
        <x-nawasara-ui::filter-dropdown
            :label="'Periode: ' . ($periods[$period] ?? '24 Jam')"
            model="period" :items="$periods" />
    </x-nawasara-ui::filter-bar>

    @if ($zone && !empty($this->analytics['error']))
        <div class="mb-6 p-4 rounded-xl border border-rose-200 bg-rose-50 dark:bg-rose-900/20 dark:border-rose-800">
            <div class="flex gap-3">
                <x-lucide-triangle-alert class="size-5 flex-shrink-0 text-rose-600 dark:text-rose-400 mt-0.5" />
                <div class="text-sm">
                    <p class="font-semibold text-rose-800 dark:text-rose-300">Gagal memuat analytics</p>
                    <p class="mt-1 text-rose-700 dark:text-rose-400 break-all">{{ $this->analytics['error'] }}</p>
                    <p class="mt-2 text-xs text-rose-600 dark:text-rose-400">
                        Pastikan API Token memiliki permission <code class="font-mono">Account Analytics: Read</code> dan <code class="font-mono">Zone Analytics: Read</code>.
                    </p>
                </div>
            </div>
        </div>
    @endif

    @if ($zone && $this->analytics && empty($this->analytics['error']))
        @php
            $totals = $this->analytics['totals'] ?? [];
            $requests = $totals['requests'] ?? [];
            $bandwidth = $totals['bandwidth'] ?? [];
            $threats = $totals['threats'] ?? [];
            $uniques = $totals['uniques'] ?? [];
            $pageviews = $totals['pageviews'] ?? [];

            $httpStatus = $requests['http_status'] ?? [];
            $contentTypes = $bandwidth['content_type'] ?? [];
            $countries = $requests['country'] ?? [];

            // Build cache hit rate untuk description sub-label
            $totalReq = $requests['all'] ?? 0;
            $cachedReq = $requests['cached'] ?? 0;
            $cacheRate = $totalReq > 0 ? round(($cachedReq / $totalReq) * 100, 1) : 0;

            $totalBw = $bandwidth['all'] ?? 0;
            $cachedBw = $bandwidth['cached'] ?? 0;
            $bwSavedPct = $totalBw > 0 ? round(($cachedBw / $totalBw) * 100, 1) : 0;
        @endphp

        {{-- Hero stats — pakai stat-card component untuk konsistensi dengan
             dashboard /home dan keycloak. Description sub-label kasih
             insight tambahan (cache hit rate) tanpa second row. --}}
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
                :description="number_format($pageviews['all'] ?? 0).' pageviews'"
                accent />
        </div>

        {{-- Details Section --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- HTTP Status Codes — bar color sesuai HTTP class semantik:
                 5xx=danger (rose), 4xx=warning (amber), 3xx=info (cyan), 2xx=success (green).
                 Ini valid data viz, bukan brand element — boleh non-emerald. --}}
            @if (!empty($httpStatus))
                <div class="bg-white dark:bg-neutral-800 rounded-xl border border-gray-200 dark:border-neutral-700 p-5">
                    <h4 class="font-semibold text-gray-700 dark:text-neutral-300 mb-3">HTTP Status Codes</h4>
                    <div class="space-y-2">
                        @foreach (collect($httpStatus)->sortByDesc(fn ($v) => $v)->take(10) as $code => $count)
                            @php
                                $pct = ($requests['all'] ?? 1) > 0 ? round(($count / $requests['all']) * 100, 1) : 0;
                                $barColor = match(true) {
                                    $code >= 500 => 'bg-rose-500',
                                    $code >= 400 => 'bg-amber-500',
                                    $code >= 300 => 'bg-cyan-500',
                                    default => 'bg-emerald-500',
                                };
                            @endphp
                            <div class="flex items-center gap-3 text-sm">
                                <span class="w-10 font-mono text-gray-600 dark:text-neutral-400">{{ $code }}</span>
                                <div class="flex-1 bg-gray-100 dark:bg-neutral-700 rounded-full h-2">
                                    <div class="{{ $barColor }} h-2 rounded-full" style="width: {{ min($pct, 100) }}%"></div>
                                </div>
                                <span class="w-20 text-right text-gray-500 dark:text-neutral-400">{{ number_format($count) }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Top Countries — bar color emerald (brand) untuk konsistensi
                 visual dengan dashboard. --}}
            @if (!empty($countries))
                <div class="bg-white dark:bg-neutral-800 rounded-xl border border-gray-200 dark:border-neutral-700 p-5">
                    <h4 class="font-semibold text-gray-700 dark:text-neutral-300 mb-3">Top Countries</h4>
                    <div class="space-y-2">
                        @foreach (collect($countries)->sortByDesc(fn ($v) => $v)->take(10) as $country => $count)
                            @php
                                $pct = ($requests['all'] ?? 1) > 0 ? round(($count / $requests['all']) * 100, 1) : 0;
                            @endphp
                            <div class="flex items-center gap-3 text-sm">
                                <span class="w-8 font-mono text-gray-600 dark:text-neutral-400">{{ $country }}</span>
                                <div class="flex-1 bg-gray-100 dark:bg-neutral-700 rounded-full h-2">
                                    <div class="bg-emerald-600 h-2 rounded-full" style="width: {{ min($pct, 100) }}%"></div>
                                </div>
                                <span class="w-20 text-right text-gray-500 dark:text-neutral-400">{{ number_format($count) }} ({{ $pct }}%)</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    @elseif (!$zone)
        {{-- Premium empty state — pilih zone --}}
        <div class="text-center py-16 px-6 border-2 border-dashed border-gray-200 dark:border-neutral-700 rounded-xl bg-gray-50/50 dark:bg-neutral-900/40">
            <div class="inline-flex items-center justify-center size-14 rounded-2xl bg-gray-100 dark:bg-neutral-800 mb-4">
                <x-lucide-bar-chart-3 class="size-7 text-gray-400 dark:text-neutral-500" />
            </div>
            <p class="text-base font-semibold text-gray-800 dark:text-neutral-200">
                Pilih zone untuk melihat analytics
            </p>
            <p class="mt-2 text-sm text-gray-500 dark:text-neutral-400 max-w-sm mx-auto">
                Filter di atas — pilih salah satu domain dan periode waktu untuk lihat traffic, bandwidth, threats, dan visitor.
            </p>
        </div>
    @endif
</div>
