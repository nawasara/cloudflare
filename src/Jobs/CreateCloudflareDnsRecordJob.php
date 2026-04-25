<?php

namespace Nawasara\Cloudflare\Jobs;

use Nawasara\Cloudflare\Models\CloudflareDnsRecord;
use Nawasara\Cloudflare\Models\CloudflareZone;

class CreateCloudflareDnsRecordJob extends AbstractCloudflareDnsJob
{
    protected function action(): string
    {
        return 'dns_create';
    }

    protected function shouldCheckConflict(): bool
    {
        return false;
    }

    protected function targetId(): ?string
    {
        return $this->payload['name'] ?? null;
    }

    protected function execute(): array
    {
        $zoneId = $this->payload['zone_id'];

        $data = [
            'type' => $this->payload['type'],
            'name' => $this->payload['name'],
            'content' => $this->payload['content'],
            'ttl' => $this->payload['ttl'] ?? 1,
            'proxied' => (bool) ($this->payload['proxied'] ?? false),
        ];

        if (! empty($this->payload['priority'])) {
            $data['priority'] = (int) $this->payload['priority'];
        }
        if (! empty($this->payload['comment'])) {
            $data['comment'] = $this->payload['comment'];
        }

        $result = $this->client()->createDnsRecord($zoneId, $data);

        if (! $result || empty($result['id'])) {
            throw new \RuntimeException('Cloudflare rejected create DNS record');
        }

        $zone = CloudflareZone::where('zone_id', $zoneId)->first();

        $attrs = [
            'record_id' => $result['id'],
            'zone_id' => $zoneId,
            'zone_name' => $zone?->name ?? '',
            'name' => $result['name'],
            'type' => $result['type'],
            'content' => $result['content'],
            'ttl' => $result['ttl'] ?? 1,
            'proxied' => (bool) ($result['proxied'] ?? false),
            'priority' => $result['priority'] ?? null,
            'comment' => $result['comment'] ?? null,
            'cf_created_at' => isset($result['created_on']) ? \Carbon\Carbon::parse($result['created_on']) : now(),
            'cf_modified_at' => isset($result['modified_on']) ? \Carbon\Carbon::parse($result['modified_on']) : now(),
        ];

        $record = CloudflareDnsRecord::create($attrs);
        $record->content_hash = $record->computeContentHash();
        $record->markSynced();
        $record->save();

        return ['record_id' => $result['id'], 'name' => $result['name']];
    }
}
