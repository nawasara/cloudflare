<?php

namespace Nawasara\Cloudflare\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Nawasara\Vault\Facades\Vault;

class CloudflareClient
{
    protected ?string $apiToken = null;
    protected ?string $accountId = null;

    protected function credentials(): array
    {
        return [
            'api_token' => $this->apiToken ??= Vault::get('cloudflare', 'api_token'),
            'account_id' => $this->accountId ??= Vault::get('cloudflare', 'account_id'),
        ];
    }

    protected function api(): PendingRequest
    {
        $creds = $this->credentials();

        return Http::baseUrl('https://api.cloudflare.com/client/v4')
            ->withToken($creds['api_token'])
            ->acceptJson()
            ->timeout(15);
    }

    public function isConfigured(): bool
    {
        return Vault::has('cloudflare', 'api_token')
            && Vault::has('cloudflare', 'account_id');
    }

    // ─── Zones ──────────────────────────────────────────

    public function getZones(array $params = []): array
    {
        $creds = $this->credentials();

        $defaults = [
            'account.id' => $creds['account_id'],
            'per_page' => 50,
            'page' => 1,
        ];

        $response = $this->api()->get('/zones', array_merge($defaults, $params));

        if ($response->successful()) {
            return [
                'result' => $response->json('result', []),
                'result_info' => $response->json('result_info', []),
            ];
        }

        return ['result' => [], 'result_info' => []];
    }

    public function getZone(string $zoneId): ?array
    {
        $response = $this->api()->get("/zones/{$zoneId}");

        return $response->successful() ? $response->json('result') : null;
    }

    public function getZoneSettings(string $zoneId): array
    {
        $response = $this->api()->get("/zones/{$zoneId}/settings");

        return $response->successful() ? $response->json('result', []) : [];
    }

    /**
     * Get zones list with caching.
     */
    public function getCachedZones(): array
    {
        return Cache::remember('cloudflare_zones', config('nawasara-cloudflare.cache_ttl', 300), function () {
            $zones = [];
            $page = 1;

            do {
                $response = $this->getZones(['page' => $page, 'per_page' => 50]);
                $zones = array_merge($zones, $response['result']);
                $totalPages = $response['result_info']['total_pages'] ?? 1;
                $page++;
            } while ($page <= $totalPages);

            return $zones;
        });
    }

    // ─── DNS Records ────────────────────────────────────

    public function getDnsRecords(string $zoneId, array $params = []): array
    {
        $defaults = [
            'per_page' => config('nawasara-cloudflare.per_page', 25),
            'page' => 1,
        ];

        $response = $this->api()->get("/zones/{$zoneId}/dns_records", array_merge($defaults, $params));

        if ($response->successful()) {
            return [
                'result' => $response->json('result', []),
                'result_info' => $response->json('result_info', []),
            ];
        }

        return ['result' => [], 'result_info' => []];
    }

    public function getAllDnsRecords(string $zoneId): array
    {
        $all = [];
        $page = 1;
        do {
            $res = $this->getDnsRecords($zoneId, ['page' => $page, 'per_page' => 100]);
            $all = array_merge($all, $res['result']);
            $totalPages = $res['result_info']['total_pages'] ?? 1;
            $page++;
        } while ($page <= $totalPages);

        return $all;
    }

    public function createDnsRecord(string $zoneId, array $data): ?array
    {
        $response = $this->api()->post("/zones/{$zoneId}/dns_records", $data);

        return $response->successful() ? $response->json('result') : null;
    }

    public function updateDnsRecord(string $zoneId, string $recordId, array $data): ?array
    {
        $response = $this->api()->put("/zones/{$zoneId}/dns_records/{$recordId}", $data);

        return $response->successful() ? $response->json('result') : null;
    }

    public function deleteDnsRecord(string $zoneId, string $recordId): bool
    {
        return $this->api()->delete("/zones/{$zoneId}/dns_records/{$recordId}")->successful();
    }

    // ─── SSL/TLS ────────────────────────────────────────

    public function getSslSetting(string $zoneId): ?string
    {
        $response = $this->api()->get("/zones/{$zoneId}/settings/ssl");

        return $response->successful() ? $response->json('result.value') : null;
    }

    public function setSslMode(string $zoneId, string $mode): bool
    {
        return $this->api()->patch("/zones/{$zoneId}/settings/ssl", [
            'value' => $mode,
        ])->successful();
    }

    // ─── Security Level / Under Attack Mode ───────────────

    public function getSecurityLevel(string $zoneId): ?string
    {
        $response = $this->api()->get("/zones/{$zoneId}/settings/security_level");

        return $response->successful() ? $response->json('result.value') : null;
    }

    public function setSecurityLevel(string $zoneId, string $level): bool
    {
        return $this->api()->patch("/zones/{$zoneId}/settings/security_level", [
            'value' => $level,
        ])->successful();
    }

    // ─── Firewall Rules ─────────────────────────────────

    public function getFirewallRules(string $zoneId, array $params = []): array
    {
        $response = $this->api()->get("/zones/{$zoneId}/firewall/rules", $params);

        return $response->successful() ? $response->json('result', []) : [];
    }

    public function createFirewallRule(string $zoneId, array $data): ?array
    {
        $response = $this->api()->post("/zones/{$zoneId}/firewall/rules", [$data]);

        return $response->successful() ? $response->json('result.0') : null;
    }

    public function updateFirewallRule(string $zoneId, string $ruleId, array $data): bool
    {
        return $this->api()->put("/zones/{$zoneId}/firewall/rules/{$ruleId}", $data)->successful();
    }

    public function deleteFirewallRule(string $zoneId, string $ruleId): bool
    {
        return $this->api()->delete("/zones/{$zoneId}/firewall/rules/{$ruleId}")->successful();
    }

    // ─── Analytics ──────────────────────────────────────

    /**
     * Fetch zone analytics via Cloudflare GraphQL Analytics API.
     * The legacy REST endpoint (/zones/{id}/analytics/dashboard) is deprecated
     * and returns empty data on most plans.
     *
     * @param string $since Period in minutes (negative, e.g. "-1440" = last 24h)
     */
    public function getAnalytics(string $zoneId, string $since = '-1440'): array
    {
        $minutes = abs((int) $since);
        if ($minutes <= 0) {
            $minutes = 1440;
        }

        $sinceTime = now()->subMinutes($minutes);
        $untilTime = now();

        // Free plan limits httpRequests1hGroups to a 3-day window.
        // Use daily groups for anything wider than 3 days.
        $useDaily = $minutes > 4320;
        $dataset = $useDaily ? 'httpRequests1dGroups' : 'httpRequests1hGroups';
        $limit = $useDaily ? 90 : 200;

        // httpRequests1dGroups uses Date scalar + date_* filter args,
        // httpRequests1hGroups uses Time scalar + datetime_* filter args.
        $timeScalar = $useDaily ? 'Date' : 'Time';
        $filterExpr = $useDaily
            ? 'date_geq: $since, date_lt: $until'
            : 'datetime_geq: $since, datetime_lt: $until';
        $sinceVar = $useDaily
            ? $sinceTime->toDateString()
            : $sinceTime->toIso8601ZuluString();
        $untilVar = $useDaily
            ? $untilTime->toDateString()
            : $untilTime->toIso8601ZuluString();

        $query = <<<GQL
        query(\$zoneTag: String!, \$since: {$timeScalar}!, \$until: {$timeScalar}!) {
          viewer {
            zones(filter: {zoneTag: \$zoneTag}) {
              series: {$dataset}(
                limit: {$limit}
                filter: {{$filterExpr}}
              ) {
                sum {
                  requests
                  cachedRequests
                  bytes
                  cachedBytes
                  threats
                  responseStatusMap { edgeResponseStatus requests }
                  countryMap { clientCountryName requests }
                }
                uniq { uniques }
              }
            }
          }
        }
        GQL;

        $response = $this->api()->post('/graphql', [
            'query' => $query,
            'variables' => [
                'zoneTag' => $zoneId,
                'since' => $sinceVar,
                'until' => $untilVar,
            ],
        ]);

        if (! $response->successful()) {
            return $this->emptyAnalytics('Cloudflare API error: HTTP ' . $response->status());
        }

        $body = $response->json();

        if (! empty($body['errors'])) {
            $messages = collect($body['errors'])
                ->pluck('message')
                ->filter()
                ->implode('; ');

            return $this->emptyAnalytics($messages ?: 'Unknown GraphQL error');
        }

        $series = data_get($body, 'data.viewer.zones.0.series', []);

        $totalRequests = 0;
        $cachedRequests = 0;
        $totalBytes = 0;
        $cachedBytes = 0;
        $threats = 0;
        $uniques = 0;
        $statusMap = [];
        $countryMap = [];

        foreach ($series as $group) {
            $sum = $group['sum'] ?? [];
            $totalRequests += (int) ($sum['requests'] ?? 0);
            $cachedRequests += (int) ($sum['cachedRequests'] ?? 0);
            $totalBytes += (int) ($sum['bytes'] ?? 0);
            $cachedBytes += (int) ($sum['cachedBytes'] ?? 0);
            $threats += (int) ($sum['threats'] ?? 0);
            $uniques += (int) ($group['uniq']['uniques'] ?? 0);

            foreach ($sum['responseStatusMap'] ?? [] as $row) {
                $code = (string) ($row['edgeResponseStatus'] ?? '');
                if ($code === '') {
                    continue;
                }
                $statusMap[$code] = ($statusMap[$code] ?? 0) + (int) ($row['requests'] ?? 0);
            }

            foreach ($sum['countryMap'] ?? [] as $row) {
                $country = (string) ($row['clientCountryName'] ?? '');
                if ($country === '') {
                    continue;
                }
                $countryMap[$country] = ($countryMap[$country] ?? 0) + (int) ($row['requests'] ?? 0);
            }
        }

        return [
            'totals' => [
                'requests' => [
                    'all' => $totalRequests,
                    'cached' => $cachedRequests,
                    'uncached' => max(0, $totalRequests - $cachedRequests),
                    'http_status' => $statusMap,
                    'country' => $countryMap,
                ],
                'bandwidth' => [
                    'all' => $totalBytes,
                    'cached' => $cachedBytes,
                    'uncached' => max(0, $totalBytes - $cachedBytes),
                ],
                'threats' => ['all' => $threats],
                'uniques' => ['all' => $uniques],
            ],
            'error' => null,
        ];
    }

    /**
     * Aggregate analytics across multiple zones in one GraphQL query.
     * Returns totals + per-zone breakdown keyed by zoneTag.
     */
    public function getAggregatedAnalytics(array $zoneIds, string $since = '-1440'): array
    {
        $zoneIds = array_values(array_filter($zoneIds));
        if (empty($zoneIds)) {
            return $this->emptyAggregated();
        }

        $minutes = abs((int) $since);
        if ($minutes <= 0) {
            $minutes = 1440;
        }

        $sinceTime = now()->subMinutes($minutes);
        $untilTime = now();

        $useDaily = $minutes > 4320;
        $dataset = $useDaily ? 'httpRequests1dGroups' : 'httpRequests1hGroups';
        $limit = $useDaily ? 100 : 200;
        $timeScalar = $useDaily ? 'Date' : 'Time';
        $filterExpr = $useDaily
            ? 'date_geq: $since, date_lt: $until'
            : 'datetime_geq: $since, datetime_lt: $until';
        $sinceVar = $useDaily
            ? $sinceTime->toDateString()
            : $sinceTime->toIso8601ZuluString();
        $untilVar = $useDaily
            ? $untilTime->toDateString()
            : $untilTime->toIso8601ZuluString();

        $query = <<<GQL
        query(\$tags: [String!]!, \$since: {$timeScalar}!, \$until: {$timeScalar}!) {
          viewer {
            zones(filter: {zoneTag_in: \$tags}, limit: 100) {
              zoneTag
              series: {$dataset}(
                limit: {$limit}
                filter: {{$filterExpr}}
              ) {
                sum {
                  requests
                  cachedRequests
                  bytes
                  cachedBytes
                  threats
                  responseStatusMap { edgeResponseStatus requests }
                  countryMap { clientCountryName requests }
                }
                uniq { uniques }
              }
            }
          }
        }
        GQL;

        $response = $this->api()->post('/graphql', [
            'query' => $query,
            'variables' => [
                'tags' => $zoneIds,
                'since' => $sinceVar,
                'until' => $untilVar,
            ],
        ]);

        if (! $response->successful()) {
            return $this->emptyAggregated('Cloudflare API error: HTTP ' . $response->status());
        }

        $body = $response->json();
        if (! empty($body['errors'])) {
            $messages = collect($body['errors'])->pluck('message')->filter()->implode('; ');
            return $this->emptyAggregated($messages ?: 'Unknown GraphQL error');
        }

        $zones = data_get($body, 'data.viewer.zones', []);

        $totalReq = 0;
        $totalCachedReq = 0;
        $totalBytes = 0;
        $totalCachedBytes = 0;
        $totalThreats = 0;
        $totalUniques = 0;
        $statusMap = [];
        $countryMap = [];
        $perZone = [];

        foreach ($zones as $zone) {
            $zoneTag = $zone['zoneTag'] ?? null;
            if (! $zoneTag) {
                continue;
            }

            $zReq = 0; $zCachedReq = 0; $zBytes = 0; $zCachedBytes = 0; $zThreats = 0; $zUniques = 0;

            foreach ($zone['series'] ?? [] as $group) {
                $sum = $group['sum'] ?? [];
                $zReq += (int) ($sum['requests'] ?? 0);
                $zCachedReq += (int) ($sum['cachedRequests'] ?? 0);
                $zBytes += (int) ($sum['bytes'] ?? 0);
                $zCachedBytes += (int) ($sum['cachedBytes'] ?? 0);
                $zThreats += (int) ($sum['threats'] ?? 0);
                $zUniques += (int) ($group['uniq']['uniques'] ?? 0);

                foreach ($sum['responseStatusMap'] ?? [] as $row) {
                    $code = (string) ($row['edgeResponseStatus'] ?? '');
                    if ($code === '') continue;
                    $statusMap[$code] = ($statusMap[$code] ?? 0) + (int) ($row['requests'] ?? 0);
                }
                foreach ($sum['countryMap'] ?? [] as $row) {
                    $country = (string) ($row['clientCountryName'] ?? '');
                    if ($country === '') continue;
                    $countryMap[$country] = ($countryMap[$country] ?? 0) + (int) ($row['requests'] ?? 0);
                }
            }

            $perZone[$zoneTag] = [
                'requests' => $zReq,
                'cached_requests' => $zCachedReq,
                'bandwidth' => $zBytes,
                'threats' => $zThreats,
                'uniques' => $zUniques,
            ];

            $totalReq += $zReq;
            $totalCachedReq += $zCachedReq;
            $totalBytes += $zBytes;
            $totalCachedBytes += $zCachedBytes;
            $totalThreats += $zThreats;
            $totalUniques += $zUniques;
        }

        return [
            'totals' => [
                'requests' => [
                    'all' => $totalReq,
                    'cached' => $totalCachedReq,
                    'uncached' => max(0, $totalReq - $totalCachedReq),
                    'http_status' => $statusMap,
                    'country' => $countryMap,
                ],
                'bandwidth' => [
                    'all' => $totalBytes,
                    'cached' => $totalCachedBytes,
                    'uncached' => max(0, $totalBytes - $totalCachedBytes),
                ],
                'threats' => ['all' => $totalThreats],
                'uniques' => ['all' => $totalUniques],
            ],
            'per_zone' => $perZone,
            'error' => null,
        ];
    }

    protected function emptyAggregated(?string $error = null): array
    {
        return [
            'totals' => [
                'requests' => ['all' => 0, 'cached' => 0, 'uncached' => 0, 'http_status' => [], 'country' => []],
                'bandwidth' => ['all' => 0, 'cached' => 0, 'uncached' => 0],
                'threats' => ['all' => 0],
                'uniques' => ['all' => 0],
            ],
            'per_zone' => [],
            'error' => $error,
        ];
    }

    protected function emptyAnalytics(?string $error = null): array
    {
        return [
            'totals' => [
                'requests' => [
                    'all' => 0,
                    'cached' => 0,
                    'uncached' => 0,
                    'http_status' => [],
                    'country' => [],
                ],
                'bandwidth' => ['all' => 0, 'cached' => 0, 'uncached' => 0],
                'threats' => ['all' => 0],
                'uniques' => ['all' => 0],
            ],
            'error' => $error,
        ];
    }

    // ─── Audit Logs ─────────────────────────────────────

    /**
     * Fetch account-level audit logs from Cloudflare.
     * Returns ['ok' => bool, 'data' => [], 'result_info' => [], 'error' => ?string].
     * Graceful when the token lacks "Audit Logs:Read".
     *
     * Supported params (Cloudflare API):
     *   since, before (RFC3339 datetime)
     *   actor.email, actor.ip
     *   zone.name
     *   direction (asc|desc), page, per_page
     */
    public function getAuditLogs(array $params = []): array
    {
        $creds = $this->credentials();
        $accountId = $creds['account_id'];
        if (! $accountId) {
            return $this->emptyAuditLogs('Account ID belum dikonfigurasi di Vault');
        }

        $defaults = [
            'per_page' => 25,
            'page' => 1,
            'direction' => 'desc',
        ];

        // Audit logs endpoint requires Global API Key (legacy auth) or
        // super-admin role; standard API tokens cannot access it. If
        // Vault has a global_api_key + email, use that for this call.
        $globalKey = Vault::has('cloudflare', 'global_api_key') ? Vault::get('cloudflare', 'global_api_key') : null;
        $email = Vault::has('cloudflare', 'email') ? Vault::get('cloudflare', 'email') : null;

        if ($globalKey && $email) {
            $response = Http::baseUrl('https://api.cloudflare.com/client/v4')
                ->withHeaders([
                    'X-Auth-Email' => $email,
                    'X-Auth-Key' => $globalKey,
                ])
                ->acceptJson()
                ->timeout(15)
                ->get("/accounts/{$accountId}/audit_logs", array_merge($defaults, $params));
        } else {
            $response = $this->api()->get("/accounts/{$accountId}/audit_logs", array_merge($defaults, $params));
        }

        if (! $response->successful()) {
            $msg = $response->json('errors.0.message') ?? ('HTTP ' . $response->status());
            if ($response->status() === 403 || $response->status() === 401) {
                $msg = 'API Token tidak bisa akses audit log. Endpoint ini membutuhkan Global API Key (legacy auth) atau role super-administrator pada account.';
            }
            return $this->emptyAuditLogs($msg);
        }

        return [
            'ok' => true,
            'data' => $response->json('result', []),
            'result_info' => $response->json('result_info', []),
            'error' => null,
        ];
    }

    protected function emptyAuditLogs(?string $error = null): array
    {
        return [
            'ok' => false,
            'data' => [],
            'result_info' => [],
            'error' => $error,
        ];
    }

    // ─── Health ─────────────────────────────────────────

    public function getDnssec(string $zoneId): ?array
    {
        $response = $this->api()->get("/zones/{$zoneId}/dnssec");

        return $response->successful() ? $response->json('result') : null;
    }

    /**
     * Returns ['ok' => bool, 'data' => array, 'error' => ?string].
     * Graceful when the token lacks "SSL and Certificates:Read".
     */
    public function getCertificatePacks(string $zoneId): array
    {
        $response = $this->api()->get("/zones/{$zoneId}/ssl/certificate_packs", [
            'status' => 'all',
        ]);

        if ($response->successful()) {
            return ['ok' => true, 'data' => $response->json('result') ?? [], 'error' => null];
        }

        $error = $response->json('errors.0.message') ?? ('HTTP ' . $response->status());
        if ($response->status() === 403) {
            $error = 'Token tidak punya permission "SSL and Certificates:Read"';
        }

        return ['ok' => false, 'data' => [], 'error' => $error];
    }

    public function getZoneSetting(string $zoneId, string $setting): ?array
    {
        $response = $this->api()->get("/zones/{$zoneId}/settings/{$setting}");

        return $response->successful() ? $response->json('result') : null;
    }

    /**
     * Compute aggregated health state for a zone.
     * Cached for 10 minutes to avoid hammering the API on dashboard refresh.
     */
    public function getZoneHealth(array $zone): array
    {
        $zoneId = $zone['id'];
        $cacheKey = "cloudflare_health:{$zoneId}";

        return Cache::remember($cacheKey, 600, function () use ($zone, $zoneId) {
            $dnssec = $this->getDnssec($zoneId);
            $packsResult = $this->getCertificatePacks($zoneId);
            $sslMode = $this->getSslSetting($zoneId);
            $alwaysOnline = $this->getZoneSetting($zoneId, 'always_online');

            // Find the soonest cert expiry from active certs covering the apex.
            $soonestExpiry = null;
            $certStatus = 'none';
            foreach ($packsResult['data'] as $pack) {
                foreach ($pack['certificates'] ?? [] as $cert) {
                    $status = $cert['status'] ?? '';
                    if (! in_array($status, ['active', 'pending_validation', 'pending_issuance'], true)) {
                        continue;
                    }
                    $expiresOn = $cert['expires_on'] ?? null;
                    if (! $expiresOn) {
                        continue;
                    }
                    $ts = strtotime($expiresOn);
                    if ($soonestExpiry === null || $ts < $soonestExpiry) {
                        $soonestExpiry = $ts;
                        $certStatus = $status;
                    }
                }
            }

            $daysToExpiry = $soonestExpiry ? (int) floor(($soonestExpiry - time()) / 86400) : null;

            $dnssecStatus = $dnssec['status'] ?? 'unknown';

            $checks = [
                'ssl_mode' => [
                    'label' => 'SSL/TLS Mode',
                    'value' => $sslMode ?? 'unknown',
                    'state' => match ($sslMode) {
                        'strict', 'full' => 'ok',
                        'flexible' => 'warning',
                        'off' => 'critical',
                        default => 'unknown',
                    },
                ],
                'cert_expiry' => [
                    'label' => 'Certificate Expiry',
                    'value' => match (true) {
                        ! $packsResult['ok'] => 'tidak bisa dicek',
                        $daysToExpiry !== null => $daysToExpiry . ' hari',
                        default => 'no active cert',
                    },
                    'days' => $daysToExpiry,
                    'cert_status' => $certStatus,
                    'hint' => $packsResult['error'],
                    'state' => match (true) {
                        ! $packsResult['ok'] => 'unknown',
                        $daysToExpiry === null => 'warning',
                        $daysToExpiry < 0 => 'critical',
                        $daysToExpiry <= 14 => 'warning',
                        $daysToExpiry <= 30 => 'warning',
                        default => 'ok',
                    },
                ],
                'dnssec' => [
                    'label' => 'DNSSEC',
                    'value' => $dnssecStatus,
                    'state' => match ($dnssecStatus) {
                        'active' => 'ok',
                        'pending' => 'warning',
                        'disabled' => 'warning',
                        default => 'unknown',
                    },
                ],
                'always_online' => [
                    'label' => 'Always Online',
                    'value' => $alwaysOnline['value'] ?? 'unknown',
                    'state' => ($alwaysOnline['value'] ?? null) === 'on' ? 'ok' : 'info',
                ],
                'zone_status' => [
                    'label' => 'Zone Status',
                    'value' => $zone['status'] ?? 'unknown',
                    'state' => ($zone['status'] ?? null) === 'active' ? 'ok' : 'warning',
                ],
            ];

            // Overall state = worst of all checks (excluding info).
            $priority = ['critical' => 3, 'warning' => 2, 'unknown' => 1, 'ok' => 0, 'info' => 0];
            $overall = 'ok';
            foreach ($checks as $check) {
                if (($priority[$check['state']] ?? 0) > ($priority[$overall] ?? 0)) {
                    $overall = $check['state'];
                }
            }

            return [
                'zone_id' => $zoneId,
                'zone_name' => $zone['name'] ?? '',
                'overall' => $overall,
                'checks' => $checks,
                'refreshed_at' => now()->toIso8601String(),
            ];
        });
    }

    public function forgetZoneHealth(string $zoneId): void
    {
        Cache::forget("cloudflare_health:{$zoneId}");
    }

    // ─── Cache ──────────────────────────────────────────

    public function purgeAllCache(string $zoneId): bool
    {
        return $this->api()->post("/zones/{$zoneId}/purge_cache", [
            'purge_everything' => true,
        ])->successful();
    }

    public function purgeUrls(string $zoneId, array $urls): bool
    {
        return $this->api()->post("/zones/{$zoneId}/purge_cache", [
            'files' => $urls,
        ])->successful();
    }
}
