<?php

namespace Nawasara\Cloudflare\Repositories;

use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Nawasara\Cloudflare\Jobs\SyncCloudflareZonesJob;
use Nawasara\Cloudflare\Models\CloudflareZone;
use Nawasara\Sync\Concerns\TracksLastSync;
use Nawasara\Sync\Contracts\SyncedRepository;
use Nawasara\Sync\Models\SyncJob;

class CloudflareZoneRepository implements SyncedRepository
{
    use TracksLastSync;

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
        return $this->lastSuccessfulSyncAt('cloudflare', 'sync_zones');
    }

    /**
     * Aggregate stats untuk hero stats row di Zones page.
     *
     * KPI yang dipilih:
     * - total: jumlah zone yang ter-track
     * - active: zone yang status-nya 'active' di Cloudflare (bukan pending/moved)
     * - ssl_strict: zone dengan SSL mode 'full' atau 'strict' (security indicator —
     *   'flexible' / 'off' adalah konfigurasi rawan MITM)
     * - dns_records: total DNS record di seluruh zone (capacity / DNS hygiene)
     */
    public function stats(): array
    {
        $row = CloudflareZone::query()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_count")
            ->selectRaw("SUM(CASE WHEN ssl_mode IN ('full', 'strict') THEN 1 ELSE 0 END) as ssl_strict_count")
            ->selectRaw('SUM(dns_records_count) as dns_total')
            ->first();

        return [
            'total' => (int) ($row?->total ?? 0),
            'active' => (int) ($row?->active_count ?? 0),
            'ssl_strict' => (int) ($row?->ssl_strict_count ?? 0),
            'dns_records' => (int) ($row?->dns_total ?? 0),
        ];
    }

    protected function query(array $filters = [])
    {
        return CloudflareZone::query()
            ->search($filters['search'] ?? null)
            ->status($filters['status'] ?? null);
    }
}
