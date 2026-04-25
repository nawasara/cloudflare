<?php

namespace Nawasara\Cloudflare\Repositories;

use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Nawasara\Cloudflare\Jobs\SyncCloudflareZonesJob;
use Nawasara\Cloudflare\Models\CloudflareZone;
use Nawasara\Sync\Contracts\SyncedRepository;
use Nawasara\Sync\Models\SyncJob;

class CloudflareZoneRepository implements SyncedRepository
{
    public function list(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return $this->query($filters)->orderBy('name')->paginate($perPage);
    }

    public function find(string|int $id): ?Model
    {
        if (is_numeric($id)) {
            return CloudflareZone::find($id);
        }
        // by zone_id (UUID) or by name
        return CloudflareZone::where('zone_id', $id)->orWhere('name', $id)->first();
    }

    public function all(array $filters = []): Collection
    {
        return $this->query($filters)->orderBy('name')->get();
    }

    public function create(array $data): ?SyncJob
    {
        // CF zones tidak biasa di-create dari Nawasara — biasanya add-zone via Cloudflare console
        throw new \BadMethodCallException('Creating zones via Nawasara is not supported.');
    }

    public function update(string|int $id, array $data): ?SyncJob
    {
        // Zone settings update ditangani per-action (setSslMode, setSecurityLevel, etc.)
        // Untuk MVP tidak expose generic update
        throw new \BadMethodCallException('Use specific zone setting methods.');
    }

    public function delete(string|int $id): ?SyncJob
    {
        throw new \BadMethodCallException('Deleting zones via Nawasara is not supported.');
    }

    public function syncNow(): ?SyncJob
    {
        SyncCloudflareZonesJob::dispatch(triggerSource: 'manual');
        return SyncJob::query()
            ->where('service', 'cloudflare')
            ->where('action', 'sync_zones')
            ->latest('id')
            ->first();
    }

    public function lastSyncedAt(): ?Carbon
    {
        $latest = CloudflareZone::whereNotNull('last_synced_at')
            ->orderByDesc('last_synced_at')
            ->value('last_synced_at');

        return $latest ? Carbon::parse($latest) : null;
    }

    protected function query(array $filters = [])
    {
        return CloudflareZone::query()
            ->search($filters['search'] ?? null)
            ->status($filters['status'] ?? null);
    }
}
