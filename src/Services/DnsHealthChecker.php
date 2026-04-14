<?php

namespace Nawasara\Cloudflare\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class DnsHealthChecker
{
    public const CACHE_TTL = 1800; // 30 minutes
    public const HTTP_TIMEOUT = 8;
    public const SSL_TIMEOUT = 5;

    /**
     * Check HTTP reachability only (fast, bulk-friendly).
     * Returns: ['identifier', 'url', 'status_code', 'final_url',
     *           'response_time_ms', 'error', 'checked_at']
     */
    public function probeHttp(string $identifier): array
    {
        $url = 'https://' . $identifier;
        $start = microtime(true);

        try {
            $response = Http::withOptions([
                'allow_redirects' => ['max' => 3, 'strict' => false, 'track_redirects' => true],
                'verify' => false,
            ])
                ->timeout(self::HTTP_TIMEOUT)
                ->withUserAgent('Nawasara-HealthChecker/1.0')
                ->get($url);

            $elapsed = (int) round((microtime(true) - $start) * 1000);
            $finalUrl = $response->handlerStats()['url'] ?? $url;

            $result = [
                'identifier' => $identifier,
                'url' => $url,
                'status_code' => $response->status(),
                'final_url' => $finalUrl,
                'response_time_ms' => $elapsed,
                'error' => null,
                'checked_at' => now()->toIso8601String(),
            ];
        } catch (\Throwable $e) {
            $elapsed = (int) round((microtime(true) - $start) * 1000);
            $result = [
                'identifier' => $identifier,
                'url' => $url,
                'status_code' => null,
                'final_url' => null,
                'response_time_ms' => $elapsed,
                'error' => $this->shortError($e->getMessage()),
                'checked_at' => now()->toIso8601String(),
            ];
        }

        $cached = $this->getCached($identifier);
        $merged = array_merge($cached ?? [], $result);
        Cache::put($this->cacheKey($identifier), $merged, self::CACHE_TTL);

        return $merged;
    }

    /**
     * Check SSL certificate of an identifier via direct TLS socket.
     * Returns: ['ssl_valid_from', 'ssl_valid_to', 'ssl_days_remaining',
     *           'ssl_issuer', 'ssl_cn', 'ssl_error']
     */
    public function probeSsl(string $identifier): array
    {
        $result = [
            'ssl_valid_from' => null,
            'ssl_valid_to' => null,
            'ssl_days_remaining' => null,
            'ssl_issuer' => null,
            'ssl_cn' => null,
            'ssl_error' => null,
        ];

        $ctx = stream_context_create([
            'ssl' => [
                'capture_peer_cert' => true,
                'verify_peer' => false,
                'verify_peer_name' => false,
                'SNI_enabled' => true,
                'peer_name' => $identifier,
            ],
        ]);

        $errno = 0;
        $errstr = '';
        $client = @stream_socket_client(
            "ssl://{$identifier}:443",
            $errno,
            $errstr,
            self::SSL_TIMEOUT,
            STREAM_CLIENT_CONNECT,
            $ctx
        );

        if (! $client) {
            $result['ssl_error'] = $errstr ?: 'Connection failed';
            return $result;
        }

        $params = stream_context_get_params($client);
        @fclose($client);

        $cert = $params['options']['ssl']['peer_certificate'] ?? null;
        if (! $cert) {
            $result['ssl_error'] = 'No peer certificate';
            return $result;
        }

        $parsed = openssl_x509_parse($cert);
        if (! $parsed) {
            $result['ssl_error'] = 'Failed to parse certificate';
            return $result;
        }

        $validTo = $parsed['validTo_time_t'] ?? null;
        $validFrom = $parsed['validFrom_time_t'] ?? null;

        $result['ssl_valid_from'] = $validFrom ? date('c', $validFrom) : null;
        $result['ssl_valid_to'] = $validTo ? date('c', $validTo) : null;
        $result['ssl_days_remaining'] = $validTo ? (int) floor(($validTo - time()) / 86400) : null;
        $result['ssl_issuer'] = $parsed['issuer']['O'] ?? ($parsed['issuer']['CN'] ?? null);
        $result['ssl_cn'] = $parsed['subject']['CN'] ?? null;

        return $result;
    }

    /**
     * Full check (HTTP + SSL) for a single identifier. Writes to cache.
     */
    public function checkOne(string $identifier): array
    {
        $http = $this->probeHttp($identifier);
        $ssl = $this->probeSsl($identifier);

        $merged = array_merge($http, $ssl, ['checked_at' => now()->toIso8601String()]);
        Cache::put($this->cacheKey($identifier), $merged, self::CACHE_TTL);

        return $merged;
    }

    /**
     * Bulk HTTP check using Http::pool for concurrent requests.
     * Does NOT run SSL check (too slow for bulk). Writes each result to cache.
     *
     * @param array<string> $identifiers
     */
    public function checkManyHttp(array $identifiers, int $concurrency = 15): array
    {
        $results = [];
        $chunks = array_chunk($identifiers, $concurrency);

        foreach ($chunks as $chunk) {
            $start = microtime(true);
            $responses = Http::pool(fn ($pool) => array_map(
                fn ($id) => $pool->as($id)
                    ->withOptions(['verify' => false])
                    ->timeout(self::HTTP_TIMEOUT)
                    ->withUserAgent('Nawasara-HealthChecker/1.0')
                    ->get('https://' . $id),
                $chunk
            ));
            $elapsed = (int) round((microtime(true) - $start) * 1000);

            foreach ($chunk as $id) {
                $resp = $responses[$id] ?? null;
                $error = null;
                $status = null;
                if ($resp instanceof \Illuminate\Http\Client\ConnectionException
                    || $resp instanceof \Throwable) {
                    $error = $this->shortError($resp->getMessage());
                } elseif ($resp) {
                    $status = $resp->status();
                }

                $existing = $this->getCached($id) ?? [];
                $result = array_merge($existing, [
                    'identifier' => $id,
                    'url' => 'https://' . $id,
                    'status_code' => $status,
                    'response_time_ms' => $elapsed,
                    'error' => $error,
                    'checked_at' => now()->toIso8601String(),
                ]);

                Cache::put($this->cacheKey($id), $result, self::CACHE_TTL);
                $results[$id] = $result;
            }
        }

        return $results;
    }

    public function getCached(string $identifier): ?array
    {
        return Cache::get($this->cacheKey($identifier));
    }

    public function getCachedMany(array $identifiers): array
    {
        $out = [];
        foreach ($identifiers as $id) {
            $cached = $this->getCached($id);
            if ($cached) {
                $out[$id] = $cached;
            }
        }
        return $out;
    }

    public function forget(string $identifier): void
    {
        Cache::forget($this->cacheKey($identifier));
    }

    protected function cacheKey(string $identifier): string
    {
        return 'cloudflare_dns_health:' . strtolower($identifier);
    }

    protected function shortError(string $message): string
    {
        if (stripos($message, 'cURL error 6') !== false) return 'DNS tidak resolve';
        if (stripos($message, 'cURL error 7') !== false) return 'Connection refused';
        if (stripos($message, 'cURL error 28') !== false) return 'Timeout';
        if (stripos($message, 'cURL error 35') !== false) return 'SSL handshake gagal';
        if (stripos($message, 'cURL error 60') !== false) return 'SSL cert invalid';
        return mb_substr($message, 0, 120);
    }

    public static function overallState(array $health): string
    {
        if (empty($health)) return 'unknown';
        if (! empty($health['error'])) return 'critical';

        $status = $health['status_code'] ?? null;
        $sslDays = $health['ssl_days_remaining'] ?? null;

        if ($status === null) return 'unknown';
        if ($status >= 500) return 'critical';
        if ($status >= 400) return 'warning';

        if ($sslDays !== null) {
            if ($sslDays < 0) return 'critical';
            if ($sslDays <= 14) return 'warning';
        }

        return 'ok';
    }
}
