<?php

namespace Nawasara\Cloudflare\Services;

use Illuminate\Support\Carbon;
use Nawasara\Cloudflare\Models\EndpointHealth;

class DnsHealthChecker
{
    public const HTTP_TIMEOUT = 8;
    public const CONNECT_TIMEOUT = 5;
    public const USER_AGENT = 'Nawasara-HealthChecker/1.0';

    /**
     * Check a batch of identifiers in parallel via curl_multi.
     * Captures HTTP status, response time, and (when withSsl=true) the
     * peer certificate via CURLOPT_CERTINFO. Persists each result to the
     * nawasara_cloudflare_endpoint_health table.
     *
     * @param array<string> $identifiers
     * @return array<string,array> identifier => result row
     */
    public function checkMany(array $identifiers, bool $withSsl = false, int $concurrency = 15): array
    {
        $identifiers = array_values(array_unique(array_filter($identifiers)));
        if (empty($identifiers)) {
            return [];
        }

        $results = [];
        $chunks = array_chunk($identifiers, $concurrency);

        foreach ($chunks as $chunk) {
            $multi = curl_multi_init();
            $handles = [];

            foreach ($chunk as $id) {
                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL => 'https://' . $id,
                    CURLOPT_NOBODY => true, // HEAD-style: no body
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_MAXREDIRS => 3,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => 0,
                    CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
                    CURLOPT_TIMEOUT => self::HTTP_TIMEOUT,
                    CURLOPT_USERAGENT => self::USER_AGENT,
                    CURLOPT_CERTINFO => $withSsl,
                ]);
                $handles[$id] = $ch;
                curl_multi_add_handle($multi, $ch);
            }

            $running = null;
            do {
                curl_multi_exec($multi, $running);
                if ($running) {
                    curl_multi_select($multi, 1.0);
                }
            } while ($running > 0);

            foreach ($handles as $id => $ch) {
                $row = $this->extractRow($id, $ch, $withSsl);
                $results[$id] = $row;
                $this->persist($row, $withSsl);
                curl_multi_remove_handle($multi, $ch);
                curl_close($ch);
            }

            curl_multi_close($multi);
        }

        return $results;
    }

    /**
     * Convenience wrapper: check a single identifier with full HTTP + SSL.
     */
    public function checkOne(string $identifier, bool $withSsl = true): array
    {
        $r = $this->checkMany([$identifier], $withSsl, 1);
        return $r[$identifier] ?? [];
    }

    protected function extractRow(string $id, \CurlHandle $ch, bool $withSsl): array
    {
        $errno = curl_errno($ch);
        $errMsg = $errno ? curl_error($ch) : null;
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE) ?: null;
        $totalTime = (float) curl_getinfo($ch, CURLINFO_TOTAL_TIME);
        $elapsedMs = (int) round($totalTime * 1000);

        $error = $errno ? $this->shortError($errno, $errMsg) : null;
        // Curl reports no error but status is also missing -> connection problem
        // (e.g. server closed connection before sending headers). Treat as failure.
        if (! $error && $status === null) {
            $error = 'No response (connection closed)';
        }

        $row = [
            'identifier' => $id,
            'status_code' => $status,
            'response_time_ms' => $elapsedMs,
            'error' => $error,
            'checked_at' => now(),
        ];

        if ($withSsl) {
            $sslData = $this->extractSslFromCertinfo($ch);
            $row = array_merge($row, $sslData, ['ssl_checked_at' => now()]);
        }

        $row['state'] = $this->computeState($row);

        return $row;
    }

    protected function extractSslFromCertinfo(\CurlHandle $ch): array
    {
        $defaults = [
            'ssl_days_remaining' => null,
            'ssl_valid_to' => null,
            'ssl_issuer' => null,
            'ssl_cn' => null,
            'ssl_error' => null,
        ];

        $certInfo = curl_getinfo($ch, CURLINFO_CERTINFO);
        if (! is_array($certInfo) || empty($certInfo)) {
            // No cert captured — usually because connection failed before TLS.
            $errno = curl_errno($ch);
            if ($errno) {
                return array_merge($defaults, ['ssl_error' => $this->shortError($errno, curl_error($ch))]);
            }
            return $defaults;
        }

        // First entry is the leaf cert.
        $leaf = $certInfo[0] ?? [];
        $expireStr = $leaf['Expire date'] ?? null;
        $startStr = $leaf['Start date'] ?? null;
        $subject = $leaf['Subject'] ?? '';
        $issuer = $leaf['Issuer'] ?? '';

        $validTo = $expireStr ? strtotime($expireStr) : null;
        $days = $validTo ? (int) floor(($validTo - time()) / 86400) : null;

        return [
            'ssl_days_remaining' => $days,
            'ssl_valid_to' => $validTo ? Carbon::createFromTimestamp($validTo) : null,
            'ssl_issuer' => $this->parseDn($issuer, ['O', 'CN']),
            'ssl_cn' => $this->parseDn($subject, ['CN']),
            'ssl_error' => null,
        ];
    }

    /**
     * Pull the first matching field from a curl-formatted DN string.
     * curl returns DN as multi-line: "CN = foo\nO = bar\n..." or "CN=foo, O=bar".
     */
    protected function parseDn(string $dn, array $fields): ?string
    {
        if ($dn === '') return null;
        // Normalize separators.
        $dn = str_replace(["\r", "\n"], ',', $dn);
        $parts = preg_split('/\s*,\s*/', $dn);

        foreach ($fields as $key) {
            foreach ($parts as $part) {
                if (preg_match('/^\s*' . preg_quote($key, '/') . '\s*=\s*(.+?)\s*$/i', $part, $m)) {
                    return $m[1];
                }
            }
        }
        return null;
    }

    /**
     * State inference rules:
     *  - connection error / 5xx / SSL expired       -> critical
     *  - 404 / SSL <= 14 days                       -> warning
     *  - 2xx / 3xx / 401 / 403                      -> ok
     *  - other 4xx                                  -> warning
     *  - no status                                  -> unknown
     *
     * 401/403 on root path = endpoint alive but auth-protected (normal for OPD apps).
     */
    public function computeState(array $row): string
    {
        if (! empty($row['error'])) return 'critical';

        $status = $row['status_code'] ?? null;
        $sslDays = $row['ssl_days_remaining'] ?? null;

        if ($status === null) return 'unknown';
        if ($status >= 500) return 'critical';
        if ($sslDays !== null && $sslDays < 0) return 'critical';
        if ($status === 404) return 'warning';
        if ($sslDays !== null && $sslDays <= 14) return 'warning';
        if (in_array($status, [401, 403], true)) return 'ok';
        if ($status >= 400) return 'warning';

        return 'ok';
    }

    public static function overallState(array $health): string
    {
        return (new self())->computeState($health);
    }

    protected function persist(array $row, bool $withSsl): void
    {
        $payload = [
            'status_code' => $row['status_code'] ?? null,
            'response_time_ms' => $row['response_time_ms'] ?? null,
            'error' => $row['error'] ?? null,
            'state' => $row['state'] ?? 'unknown',
            'checked_at' => $row['checked_at'] ?? now(),
        ];

        // Only overwrite SSL fields when we actually probed SSL on this run.
        // HTTP-only checks must not wipe previously captured cert info.
        if ($withSsl) {
            $payload['ssl_days_remaining'] = $row['ssl_days_remaining'] ?? null;
            $payload['ssl_valid_to'] = $row['ssl_valid_to'] ?? null;
            $payload['ssl_issuer'] = $row['ssl_issuer'] ?? null;
            $payload['ssl_cn'] = $row['ssl_cn'] ?? null;
            $payload['ssl_error'] = $row['ssl_error'] ?? null;
            $payload['ssl_checked_at'] = $row['ssl_checked_at'] ?? now();
        }

        EndpointHealth::updateOrCreate(
            ['identifier' => $row['identifier']],
            $payload
        );
    }

    protected function shortError(int $errno, ?string $message): string
    {
        return match ($errno) {
            6 => 'DNS tidak resolve',
            7 => 'Connection refused',
            28 => 'Timeout',
            35 => 'SSL handshake gagal',
            60 => 'SSL cert invalid',
            default => 'cURL ' . $errno . ': ' . mb_substr($message ?? '', 0, 100),
        };
    }
}
