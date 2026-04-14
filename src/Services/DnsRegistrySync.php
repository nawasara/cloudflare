<?php

namespace Nawasara\Cloudflare\Services;

use Nawasara\Registry\Models\Asset;

class DnsRegistrySync
{
    /** Record types that represent actual endpoints worth tracking as subdomain assets. */
    public const TRACKED_TYPES = ['A', 'AAAA', 'CNAME'];

    public function __construct(protected CloudflareClient $cloudflare)
    {
    }

    /**
     * Sync all DNS records of a zone into the registry as subdomain assets.
     * New records inherit OPD/PIC from the parent zone asset if available.
     */
    public function syncZone(string $zoneId): array
    {
        $stats = ['total' => 0, 'created' => 0, 'linked' => 0, 'updated' => 0, 'unchanged' => 0, 'skipped' => 0];

        $zone = $this->cloudflare->getZone($zoneId);
        if (! $zone) {
            return $stats;
        }

        $zoneName = $zone['name'] ?? null;
        $records = $this->cloudflare->getAllDnsRecords($zoneId);
        $stats['total'] = count($records);

        [$defaultOpdId, $defaultPicId] = $this->parentDefaults($zoneId);

        foreach ($records as $record) {
            if (! $this->isTrackable($record, $zoneName)) {
                $stats['skipped']++;
                continue;
            }

            $outcome = $this->upsertRecord($record, $defaultOpdId, $defaultPicId);
            $stats[$outcome]++;
        }

        return $stats;
    }

    /**
     * Link a single DNS record (e.g. right after UI creation).
     * Returns the resulting Asset or null if not trackable.
     */
    public function linkRecord(string $zoneId, array $record, ?string $zoneName = null): ?Asset
    {
        if (! $zoneName) {
            $zone = $this->cloudflare->getZone($zoneId);
            $zoneName = $zone['name'] ?? null;
        }

        if (! $this->isTrackable($record, $zoneName)) {
            return null;
        }

        [$defaultOpdId, $defaultPicId] = $this->parentDefaults($zoneId);
        $this->upsertRecord($record, $defaultOpdId, $defaultPicId);

        return Asset::where('package_ref', 'cloudflare')
            ->where('external_id', $record['id'] ?? null)
            ->first();
    }

    /**
     * Remove the registry asset for a deleted DNS record.
     */
    public function unlinkRecord(string $recordId): bool
    {
        return (bool) Asset::where('package_ref', 'cloudflare')
            ->where('external_id', $recordId)
            ->where('type', 'subdomain')
            ->delete();
    }

    protected function isTrackable(array $record, ?string $zoneName): bool
    {
        if (! in_array($record['type'] ?? '', self::TRACKED_TYPES, true)) {
            return false;
        }
        // Skip zone apex — already represented by the zone-level asset.
        if ($zoneName && ($record['name'] ?? '') === $zoneName) {
            return false;
        }

        return true;
    }

    /**
     * @return array{0:?int,1:?int} [opd_id, pic_id] inherited from zone asset.
     */
    protected function parentDefaults(string $zoneId): array
    {
        $zoneAsset = Asset::where('package_ref', 'cloudflare')
            ->where('external_id', $zoneId)
            ->first();

        return [$zoneAsset?->opd_id, $zoneAsset?->pic_id];
    }

    protected function upsertRecord(array $record, ?int $defaultOpdId, ?int $defaultPicId): string
    {
        $recordId = $record['id'] ?? null;
        $identifier = $record['name'] ?? null;
        if (! $recordId || ! $identifier) {
            return 'skipped';
        }

        $asset = Asset::where('package_ref', 'cloudflare')
            ->where('external_id', $recordId)
            ->first();

        if ($asset) {
            if ($asset->identifier !== $identifier) {
                $asset->update(['identifier' => $identifier]);
                return 'updated';
            }
            return 'unchanged';
        }

        $existing = Asset::where('type', 'subdomain')
            ->where('identifier', $identifier)
            ->whereNull('external_id')
            ->first();

        if ($existing) {
            $existing->update([
                'package_ref' => 'cloudflare',
                'external_id' => $recordId,
            ]);
            return 'linked';
        }

        Asset::create([
            'opd_id' => $defaultOpdId,
            'pic_id' => $defaultPicId,
            'type' => 'subdomain',
            'identifier' => $identifier,
            'package_ref' => 'cloudflare',
            'external_id' => $recordId,
            'status' => 'active',
            'registered_at' => now(),
        ]);

        return 'created';
    }
}
