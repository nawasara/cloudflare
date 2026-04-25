<?php

namespace Nawasara\Cloudflare\Jobs;

class UpdateCloudflareDnsRecordJob extends AbstractCloudflareDnsJob
{
    protected function action(): string
    {
        return 'dns_update';
    }

    protected function execute(): array
    {
        $zoneId = $this->payload['zone_id'];
        $recordId = $this->payload['record_id'];

        $record = $this->record();
        if (! $record) {
            throw new \RuntimeException("Local record not found: {$recordId}");
        }

        // Build update payload — keep type & name from existing if not provided
        $data = [
            'type' => $this->payload['type'] ?? $record->type,
            'name' => $this->payload['name'] ?? $record->name,
            'content' => $this->payload['content'] ?? $record->content,
            'ttl' => $this->payload['ttl'] ?? $record->ttl,
            'proxied' => $this->payload['proxied'] ?? $record->proxied,
        ];

        if (isset($this->payload['priority']) || $record->priority !== null) {
            $data['priority'] = $this->payload['priority'] ?? $record->priority;
        }

        $result = $this->client()->updateDnsRecord($zoneId, $recordId, $data);

        if (! $result) {
            throw new \RuntimeException("Cloudflare rejected update for {$recordId}");
        }

        $record->fill([
            'name' => $result['name'] ?? $record->name,
            'type' => $result['type'] ?? $record->type,
            'content' => $result['content'] ?? $record->content,
            'ttl' => $result['ttl'] ?? $record->ttl,
            'proxied' => $result['proxied'] ?? $record->proxied,
            'priority' => $result['priority'] ?? $record->priority,
            'cf_modified_at' => isset($result['modified_on']) ? \Carbon\Carbon::parse($result['modified_on']) : now(),
        ]);
        $record->content_hash = $record->computeContentHash();
        $record->markSynced();
        $record->save();

        return ['record_id' => $recordId, 'name' => $record->name];
    }
}
