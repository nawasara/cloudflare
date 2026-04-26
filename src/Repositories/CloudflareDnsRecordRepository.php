<?php

namespace Nawasara\Cloudflare\Repositories;

use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Nawasara\Cloudflare\Jobs\CreateCloudflareDnsRecordJob;
use Nawasara\Cloudflare\Jobs\DeleteCloudflareDnsRecordJob;
use Nawasara\Cloudflare\Jobs\SyncCloudflareDnsRecordsJob;
use Nawasara\Cloudflare\Jobs\UpdateCloudflareDnsRecordJob;
use Nawasara\Cloudflare\Models\CloudflareDnsRecord;
use Nawasara\Sync\Concerns\TracksLastSync;
use Nawasara\Sync\Contracts\SyncedRepository;
use Nawasara\Sync\Models\SyncJob;

class CloudflareDnsRecordRepository implements SyncedRepository
{
    use TracksLastSync;

    public function __construct(public ?string $zoneId = null)
    {
    }

    public function forZone(?string $zoneId): static
    {
        return new static($zoneId ?: null);
    }

    public function list(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return $this->query($filters)->orderBy('name')->paginate($perPage);
    }

    public function find(string|int $id): ?Model
    {
        if (is_numeric($id)) {
            return CloudflareDnsRecord::find($id);
        }
        return CloudflareDnsRecord::where('record_id', $id)->first();
    }

    public function all(array $filters = []): Collection
    {
        return $this->query($filters)->orderBy('name')->get();
    }

    public function create(array $data): ?SyncJob
    {
        if (! $this->zoneId) {
            throw new \InvalidArgumentException('Zone ID required to create DNS record.');
        }

        CreateCloudflareDnsRecordJob::dispatch(
            payload: array_merge(['zone_id' => $this->zoneId], $data),
        );

        return SyncJob::query()
            ->where('service', 'cloudflare')
            ->where('action', 'dns_create')
            ->latest('id')
            ->first();
    }

    public function update(string|int $id, array $data): ?SyncJob
    {
        $record = $this->find($id);
        if (! $record) {
            throw new \InvalidArgumentException("DNS record not found: {$id}");
        }

        $expectedHash = $record->content_hash;
        $record->markPending(CloudflareDnsRecord::SYNC_PENDING_UPDATE);

        UpdateCloudflareDnsRecordJob::dispatch(
            payload: array_merge([
                'zone_id' => $record->zone_id,
                'record_id' => $record->record_id,
            ], $data),
            expectedHash: $expectedHash,
        );

        return SyncJob::query()
            ->where('service', 'cloudflare')
            ->where('action', 'dns_update')
            ->where('target_id', $record->record_id)
            ->latest('id')
            ->first();
    }

    public function delete(string|int $id): ?SyncJob
    {
        $record = $this->find($id);
        if (! $record) {
            throw new \InvalidArgumentException("DNS record not found: {$id}");
        }

        $record->markPending(CloudflareDnsRecord::SYNC_PENDING_DELETE);

        DeleteCloudflareDnsRecordJob::dispatch(
            payload: [
                'zone_id' => $record->zone_id,
                'record_id' => $record->record_id,
            ],
            expectedHash: $record->content_hash,
        );

        return SyncJob::query()
            ->where('service', 'cloudflare')
            ->where('action', 'dns_delete')
            ->where('target_id', $record->record_id)
            ->latest('id')
            ->first();
    }

    public function syncNow(): ?SyncJob
    {
        SyncCloudflareDnsRecordsJob::dispatch(
            payload: $this->zoneId ? ['zone_id' => $this->zoneId] : [],
            triggerSource: 'manual',
        );

        return SyncJob::query()
            ->where('service', 'cloudflare')
            ->where('action', 'sync_dns_records')
            ->latest('id')
            ->first();
    }

    public function lastSyncedAt(): ?Carbon
    {
        return $this->lastSuccessfulSyncAt('cloudflare', 'sync_dns_records');
    }

    protected function query(array $filters = [])
    {
        return CloudflareDnsRecord::query()
            ->forZone($filters['zone_id'] ?? $this->zoneId)
            ->search($filters['search'] ?? null)
            ->type($filters['type'] ?? null)
            ->proxied($filters['proxied'] ?? null);
    }
}
