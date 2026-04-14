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
        <div class="mb-6 p-4 rounded-xl border border-red-200 bg-red-50 dark:bg-red-900/20 dark:border-red-800">
            <div class="flex gap-3">
                <x-lucide-triangle-alert class="size-5 flex-shrink-0 text-red-600 dark:text-red-400 mt-0.5" />
                <div class="text-sm">
                    <p class="font-semibold text-red-800 dark:text-red-300">Gagal memuat analytics</p>
                    <p class="mt-1 text-red-700 dark:text-red-400 break-all">{{ $this->analytics['error'] }}</p>
                    <p class="mt-2 text-xs text-red-600 dark:text-red-400">
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
        @endphp

        {{-- Stats Cards --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            {{-- Total Requests --}}
            <div class="bg-white dark:bg-neutral-800 rounded-xl border border-gray-200 dark:border-neutral-700 p-5">
                <div class="flex items-center gap-3">
                    <div class="flex-shrink-0 size-10 flex items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-900/30">
                        <x-lucide-globe class="size-5 text-blue-600 dark:text-blue-400" />
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-neutral-400">Total Requests</p>
                        <p class="text-2xl font-bold text-gray-800 dark:text-neutral-200">
                            {{ number_format($requests['all'] ?? 0) }}
                        </p>
                    </div>
                </div>
                @if (isset($requests['cached'], $requests['uncached']))
                    <div class="mt-3 flex gap-3 text-xs">
                        <span class="text-green-600 dark:text-green-400">Cached: {{ number_format($requests['cached'] ?? 0) }}</span>
                        <span class="text-gray-400">|</span>
                        <span class="text-orange-600 dark:text-orange-400">Uncached: {{ number_format($requests['uncached'] ?? 0) }}</span>
                    </div>
                @endif
            </div>

            {{-- Bandwidth --}}
            <div class="bg-white dark:bg-neutral-800 rounded-xl border border-gray-200 dark:border-neutral-700 p-5">
                <div class="flex items-center gap-3">
                    <div class="flex-shrink-0 size-10 flex items-center justify-center rounded-lg bg-green-100 dark:bg-green-900/30">
                        <x-lucide-hard-drive class="size-5 text-green-600 dark:text-green-400" />
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-neutral-400">Bandwidth</p>
                        <p class="text-2xl font-bold text-gray-800 dark:text-neutral-200">
                            {{ $this->formatBytes($bandwidth['all'] ?? 0) }}
                        </p>
                    </div>
                </div>
                @if (isset($bandwidth['cached'], $bandwidth['uncached']))
                    <div class="mt-3 flex gap-3 text-xs">
                        <span class="text-green-600 dark:text-green-400">Cached: {{ $this->formatBytes($bandwidth['cached'] ?? 0) }}</span>
                        <span class="text-gray-400">|</span>
                        <span class="text-orange-600 dark:text-orange-400">Uncached: {{ $this->formatBytes($bandwidth['uncached'] ?? 0) }}</span>
                    </div>
                @endif
            </div>

            {{-- Threats --}}
            <div class="bg-white dark:bg-neutral-800 rounded-xl border border-gray-200 dark:border-neutral-700 p-5">
                <div class="flex items-center gap-3">
                    <div class="flex-shrink-0 size-10 flex items-center justify-center rounded-lg bg-red-100 dark:bg-red-900/30">
                        <x-lucide-shield-alert class="size-5 text-red-600 dark:text-red-400" />
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-neutral-400">Threats Blocked</p>
                        <p class="text-2xl font-bold text-gray-800 dark:text-neutral-200">
                            {{ number_format($threats['all'] ?? 0) }}
                        </p>
                    </div>
                </div>
            </div>

            {{-- Unique Visitors --}}
            <div class="bg-white dark:bg-neutral-800 rounded-xl border border-gray-200 dark:border-neutral-700 p-5">
                <div class="flex items-center gap-3">
                    <div class="flex-shrink-0 size-10 flex items-center justify-center rounded-lg bg-purple-100 dark:bg-purple-900/30">
                        <x-lucide-users class="size-5 text-purple-600 dark:text-purple-400" />
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-neutral-400">Unique Visitors</p>
                        <p class="text-2xl font-bold text-gray-800 dark:text-neutral-200">
                            {{ number_format($uniques['all'] ?? 0) }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Details Section --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- HTTP Status Codes --}}
            @if (!empty($httpStatus))
                <div class="bg-white dark:bg-neutral-800 rounded-xl border border-gray-200 dark:border-neutral-700 p-5">
                    <h4 class="font-semibold text-gray-700 dark:text-neutral-300 mb-3">HTTP Status Codes</h4>
                    <div class="space-y-2">
                        @foreach (collect($httpStatus)->sortByDesc(fn ($v) => $v)->take(10) as $code => $count)
                            @php
                                $pct = ($requests['all'] ?? 1) > 0 ? round(($count / $requests['all']) * 100, 1) : 0;
                                $barColor = match(true) {
                                    $code >= 500 => 'bg-red-500',
                                    $code >= 400 => 'bg-yellow-500',
                                    $code >= 300 => 'bg-blue-500',
                                    default => 'bg-green-500',
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

            {{-- Top Countries --}}
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
                                    <div class="bg-blue-500 h-2 rounded-full" style="width: {{ min($pct, 100) }}%"></div>
                                </div>
                                <span class="w-20 text-right text-gray-500 dark:text-neutral-400">{{ number_format($count) }} ({{ $pct }}%)</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    @elseif (!$zone)
        <div class="text-center py-12">
            <x-lucide-bar-chart-3 class="size-12 mx-auto text-gray-300 dark:text-neutral-600" />
            <p class="mt-3 text-sm text-gray-500 dark:text-neutral-400">Pilih zone terlebih dahulu untuk melihat analytics.</p>
        </div>
    @endif
</div>
