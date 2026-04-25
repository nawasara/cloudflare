<?php

namespace Nawasara\Cloudflare\Jobs;

class DeleteCloudflareDnsRecordJob extends AbstractCloudflareDnsJob
{
    protected function action(): string
    {
        return 'dns_delete';
    }

    protected function execute(): array
    {
        $zoneId = $this->payload['zone_id'];
        $recordId = $this->payload['record_id'];

        $record = $this->record();

        $ok = $this->client()->deleteDnsRecord($zoneId, $recordId);
        if (! $ok) {
            throw new \RuntimeException("Cloudflare rejected delete for {$recordId}");
        }

        $record?->delete();

        return ['record_id' => $recordId, 'deleted' => true];
    }
}
