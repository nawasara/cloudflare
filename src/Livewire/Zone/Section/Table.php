<?php

namespace Nawasara\Cloudflare\Livewire\Zone\Section;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Nawasara\Cloudflare\Services\CloudflareClient;
use Nawasara\Cloudflare\Services\ZoneRegistrySync;
use Nawasara\Registry\Models\Asset;

class Table extends Component
{
    public string $search = '';

    // Detail modal
    public bool $showDetail = false;
    public ?array $detailZone = null;
    public ?string $detailSsl = null;
    public ?string $detailSecurityLevel = null;

    // Purge cache modal
    public bool $showPurge = false;
    public string $purgeZoneId = '';
    public string $purgeZoneName = '';
    public string $purgeType = 'all';
    public string $purgeUrls = '';

    protected CloudflareClient $cloudflare;

    public function boot(CloudflareClient $cloudflare)
    {
        $this->cloudflare = $cloudflare;
    }

    #[Computed]
    public function zones()
    {
        $zones = $this->cloudflare->getCachedZones();

        if ($this->search) {
            $search = strtolower($this->search);
            $zones = array_filter($zones, fn ($zone) => str_contains(strtolower($zone['name'] ?? ''), $search));
            $zones = array_values($zones);
        }

        return $zones;
    }

    public function openDetail(string $zoneId)
    {
        $this->detailZone = $this->cloudflare->getZone($zoneId);
        $this->detailSsl = $this->cloudflare->getSslSetting($zoneId);
        $this->detailSecurityLevel = $this->cloudflare->getSecurityLevel($zoneId);
        $this->showDetail = true;
    }

    public function closeDetail()
    {
        $this->showDetail = false;
        $this->detailZone = null;
        $this->detailSsl = null;
        $this->detailSecurityLevel = null;
    }

    public function setSslMode(string $mode)
    {
        if (! $this->detailZone) {
            return;
        }

        if ($this->cloudflare->setSslMode($this->detailZone['id'], $mode)) {
            $this->detailSsl = $mode;
            toaster_success("SSL mode diubah ke {$mode}");
        } else {
            toaster_error('Gagal mengubah SSL mode');
        }
    }

    public function setSecurityLevel(string $level)
    {
        if (! $this->detailZone) {
            return;
        }

        if ($this->cloudflare->setSecurityLevel($this->detailZone['id'], $level)) {
            $this->detailSecurityLevel = $level;
            $label = $level === 'under_attack' ? 'Under Attack Mode AKTIF' : 'Security level diubah ke ' . $level;
            toaster_success($label);
        } else {
            toaster_error('Gagal mengubah security level');
        }
    }

    public function openPurge(string $zoneId, string $zoneName)
    {
        $this->purgeZoneId = $zoneId;
        $this->purgeZoneName = $zoneName;
        $this->purgeType = 'all';
        $this->purgeUrls = '';
        $this->showPurge = true;
    }

    public function doPurge()
    {
        $success = false;

        if ($this->purgeType === 'all') {
            $success = $this->cloudflare->purgeAllCache($this->purgeZoneId);
        } else {
            $urls = array_filter(array_map('trim', explode("\n", $this->purgeUrls)));
            if (empty($urls)) {
                toaster_error('Masukkan minimal 1 URL');
                return;
            }
            $success = $this->cloudflare->purgeUrls($this->purgeZoneId, $urls);
        }

        if ($success) {
            toaster_success("Cache {$this->purgeZoneName} berhasil di-purge");
            $this->showPurge = false;
        } else {
            toaster_error('Gagal purge cache');
        }
    }

    public function refreshZones()
    {
        cache()->forget('cloudflare_zones');
        unset($this->zones);
        toaster_success('Zone list di-refresh');
    }

    #[Computed]
    public function assetMap()
    {
        return Asset::query()
            ->where('package_ref', 'cloudflare')
            ->whereNotNull('external_id')
            ->with(['opd:id,name,code', 'pic:id,name'])
            ->get()
            ->keyBy('external_id');
    }

    public function syncRegistry(ZoneRegistrySync $sync)
    {
        $stats = $sync->sync();
        unset($this->assetMap);

        $parts = [];
        if ($stats['created']) $parts[] = "{$stats['created']} baru";
        if ($stats['linked']) $parts[] = "{$stats['linked']} terhubung";
        if ($stats['updated']) $parts[] = "{$stats['updated']} diperbarui";
        if (! $parts) $parts[] = 'semua up-to-date';

        toaster_success('Sync registry: ' . implode(', ', $parts) . " (dari {$stats['total']} zone)");
    }

    public function render()
    {
        return view('nawasara-cloudflare::livewire.pages.zone.section.table');
    }
}
