<?php

namespace Nawasara\Cloudflare\Services;

use Nawasara\Registry\Models\Asset;

class ZoneRegistrySync
{
    public function __construct(protected CloudflareClient $cloudflare)
    {
    }

    /**
     * Sync Cloudflare zones into the registry as domain assets.
     *
     * Flow:
     *  1. If an asset is already linked via (package_ref=cloudflare, external_id=zoneId),
     *     only refresh its identifier and status.
     *  2. Else, if there is an existing unlinked domain asset with the same identifier,
     *     attach package_ref + external_id to it (preserves manually set OPD/PIC).
     *  3. Else, create a new unassigned asset (opd_id = null) for manual assignment.
     */
    public function sync(): array
    {
        $zones = $this->cloudflare->getCachedZones();

        $stats = [
            'total' => count($zones),
            'created' => 0,
            'linked' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'deactivated' => 0,
        ];

        $seenZoneIds = [];

        foreach ($zones as $zone) {
            $zoneId = $zone['id'] ?? null;
            $zoneName = $zone['name'] ?? null;
            if (! $zoneId || ! $zoneName) {
                continue;
            }

            $seenZoneIds[] = $zoneId;
            $mappedStatus = ($zone['status'] ?? null) === 'active' ? 'active' : 'pending';

            $asset = Asset::where('package_ref', 'cloudflare')
                ->where('external_id', $zoneId)
                ->first();

            if ($asset) {
                $changes = [];
                if ($asset->identifier !== $zoneName) {
                    $changes['identifier'] = $zoneName;
                }
                // Don't override a manually set "inactive" asset.
                if ($asset->status !== 'inactive' && $asset->status !== $mappedStatus) {
                    $changes['status'] = $mappedStatus;
                }
                if ($changes) {
                    $asset->update($changes);
                    $stats['updated']++;
                } else {
                    $stats['unchanged']++;
                }
                continue;
            }

            $existing = Asset::where('type', 'domain')
                ->where('identifier', $zoneName)
                ->whereNull('external_id')
                ->first();

            if ($existing) {
                $existing->update([
                    'package_ref' => 'cloudflare',
                    'external_id' => $zoneId,
                ]);
                $stats['linked']++;
                continue;
            }

            Asset::create([
                'opd_id' => null,
                'pic_id' => null,
                'type' => 'domain',
                'identifier' => $zoneName,
                'package_ref' => 'cloudflare',
                'external_id' => $zoneId,
                'status' => $mappedStatus,
                'registered_at' => now(),
                'discovered_at' => now(),
            ]);
            $stats['created']++;
        }

        // Deactivate domain assets whose zones no longer exist in Cloudflare.
        if (! empty($seenZoneIds)) {
            $stats['deactivated'] = Asset::where('package_ref', 'cloudflare')
                ->where('type', 'domain')
                ->where('status', 'active')
                ->whereNotNull('external_id')
                ->whereNotIn('external_id', $seenZoneIds)
                ->update(['status' => 'inactive']);
        }

        return $stats;
    }
}
