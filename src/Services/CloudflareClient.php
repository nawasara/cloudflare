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

    public function getAnalytics(string $zoneId, string $since = '-1440'): array
    {
        $response = $this->api()->get("/zones/{$zoneId}/analytics/dashboard", [
            'since' => $since,
            'continuous' => true,
        ]);

        return $response->successful() ? $response->json('result', []) : [];
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
