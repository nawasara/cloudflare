<?php

namespace Nawasara\Cloudflare\Jobs;

use Nawasara\Cloudflare\Models\CloudflareDnsRecord;
use Nawasara\Cloudflare\Services\CloudflareClient;
use Nawasara\Sync\Jobs\AbstractSyncJob;

abstract class AbstractCloudflareDnsJob extends AbstractSyncJob
{
    public int $timeout = 60;

    protected function service(): string
    {
        return 'cloudflare';
    }

    protected function targetType(): ?string
    {
        return 'CloudflareDnsRecord';
    }

    protected function targetId(): ?string
    {
        return $this->payload['record_id'] ?? null;
    }

    protected function client(): CloudflareClient
    {
        return app(CloudflareClient::class);
    }

    protected function record(): ?CloudflareDnsRecord
    {
        $recordId = $this->payload['record_id'] ?? null;
        if (! $recordId) return null;
        return CloudflareDnsRecord::where('record_id', $recordId)->first();
    }

    protected function currentExternalHash(): ?string
    {
        return $this->record()?->content_hash;
    }
}
