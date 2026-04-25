<?php

namespace Nawasara\Cloudflare\Jobs;

use Nawasara\Cloudflare\Models\CloudflareDnsRecord;
use Nawasara\Cloudflare\Models\CloudflareZone;
use Nawasara\Cloudflare\Services\CloudflareClient;
use Nawasara\Sync\Jobs\AbstractSyncJob;

/**
 * Sync DNS records untuk semua zones (atau zone tertentu kalau payload['zone_id']).
 *
 * Pakai getAllDnsRecords yang sudah handle pagination per zone.
 */
class SyncCloudflareDnsRecordsJob extends AbstractSyncJob
{
    public int $timeout = 600;

    protected function service(): string
    {
        return 'cloudflare';
    }

    protected function action(): string
    {
        return 'sync_dns_records';
    }

    protected function targetType(): ?string
    {
        return 'CloudflareDnsRecord';
    }

    protected function targetId(): ?string
    {
        return $this->payload['zone_id'] ?? null;
    }

    protected function execute(): array
    {
        $cf = app(CloudflareClient::class);
        if (! $cf->isConfigured()) {
            throw new \RuntimeException('Cloudflare client not configured');
        }

        $stats = [
            'total' => 0,
            'created' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'deactivated' => 0,
            'zones_processed' => 0,
        ];

        $zoneIds = isset($this->payload['zone_id'])
            ? [$this->payload['zone_id']]
            : CloudflareZone::where('status', '!=', 'deleted')->pluck('zone_id')->all();

        foreach ($zoneIds as $zoneId) {
            $zone = CloudflareZone::where('zone_id', $zoneId)->first();
            if (! $zone) continue;

            $records = $cf->getAllDnsRecords($zoneId);
            $stats['zones_processed']++;
            $stats['total'] += count($records);

            $seenRecordIds = [];

            foreach ($records as $row) {
                $recordId = $row['id'] ?? null;
                if (! $recordId) continue;

                $seenRecordIds[] = $recordId;

                $attrs = [
                    'record_id' => $recordId,
                    'zone_id' => $zoneId,
                    'zone_name' => $zone->name,
                    'name' => $row['name'],
                    'type' => $row['type'],
                    'content' => $row['content'] ?? '',
                    'ttl' => $row['ttl'] ?? 1,
                    'proxied' => (bool) ($row['proxied'] ?? false),
                    'priority' => $row['priority'] ?? null,
                    'comment' => $row['comment'] ?? null,
                    'tags' => $row['tags'] ?? null,
                    'cf_created_at' => isset($row['created_on']) ? \Carbon\Carbon::parse($row['created_on']) : null,
                    'cf_modified_at' => isset($row['modified_on']) ? \Carbon\Carbon::parse($row['modified_on']) : null,
                    'sync_status' => CloudflareDnsRecord::SYNC_SYNCED,
                    'sync_error' => null,
                    'last_synced_at' => now(),
                ];

                $existing = CloudflareDnsRecord::where('record_id', $recordId)->first();

                if ($existing) {
                    $tempModel = new CloudflareDnsRecord(array_merge($existing->toArray(), $attrs));
                    $newHash = $tempModel->computeContentHash();

                    if ($existing->content_hash === $newHash && $existing->isSynced()) {
                        $stats['unchanged']++;
                        continue;
                    }

                    $existing->update(array_merge($attrs, ['content_hash' => $newHash]));
                    $stats['updated']++;
                } else {
                    $tempModel = new CloudflareDnsRecord($attrs);
                    $newHash = $tempModel->computeContentHash();
                    CloudflareDnsRecord::create(array_merge($attrs, ['content_hash' => $newHash]));
                    $stats['created']++;
                }
            }

            // Records yang hilang dari Cloudflare zone = delete dari DB
            if (! empty($seenRecordIds)) {
                $stats['deactivated'] += CloudflareDnsRecord::where('zone_id', $zoneId)
                    ->whereNotIn('record_id', $seenRecordIds)
                    ->where('sync_status', '!=', CloudflareDnsRecord::SYNC_PENDING_DELETE)
                    ->where('sync_status', '!=', CloudflareDnsRecord::SYNC_PENDING_CREATE)
                    ->delete();
            }
        }

        return $stats;
    }
}
