<?php

namespace Nawasara\Cloudflare\Livewire\Zone\Section;

use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Nawasara\Cloudflare\Models\CloudflareZone;
use Nawasara\Cloudflare\Repositories\CloudflareZoneRepository;
use Nawasara\Cloudflare\Services\CloudflareClient;
use Nawasara\Cloudflare\Services\ZoneRegistrySync;
use Nawasara\Registry\Models\Asset;
use Nawasara\Ui\Livewire\Concerns\HasBrowserToast;

class Table extends Component
{
    use HasBrowserToast;
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    public int $perPage = 25;

    // Detail modal
    public ?int $detailId = null;
    public ?string $detailSsl = null;
    public ?string $detailSecurityLevel = null;

    // Purge cache modal
    public string $purgeZoneId = '';
    public string $purgeZoneName = '';
    public string $purgeType = 'all';
    public string $purgeUrls = '';

    protected CloudflareClient $cloudflare;

    public function boot(CloudflareClient $cloudflare)
    {
        $this->cloudflare = $cloudflare;
    }

    protected function repo(): CloudflareZoneRepository
    {
        return new CloudflareZoneRepository();
    }

    public function updatedSearch(): void { $this->resetPage(); }

    #[Computed]
    public function zones()
    {
        return $this->repo()->list([
            'search' => $this->search ?: null,
        ], $this->perPage);
    }

    #[Computed]
    public function lastSyncedAt(): ?string
    {
        $when = $this->repo()->lastSyncedAt();
        return $when ? $when->diffForHumans() : null;
    }

    #[Computed]
    public function detail(): ?CloudflareZone
    {
        return $this->detailId ? CloudflareZone::find($this->detailId) : null;
    }

    public function refreshZones(): void
    {
        Gate::authorize('cloudflare.zone.view');

        $this->repo()->syncNow();
        $this->toastSuccess('Sync dispatched. Refresh dalam beberapa detik.');
    }

    public function openDetail(int $id): void
    {
        $this->detailId = $id;

        // Fetch live SSL & security level (small calls, OK live)
        $zone = CloudflareZone::find($id);
        if ($zone) {
            $this->detailSsl = $zone->ssl_mode ?: $this->cloudflare->getSslSetting($zone->zone_id);
            $this->detailSecurityLevel = $zone->security_level ?: $this->cloudflare->getSecurityLevel($zone->zone_id);
        }
        $this->dispatch('modal-open:zone-detail');
    }

    public function closeDetail(): void
    {
        $this->dispatch('modal-close:zone-detail');
        $this->detailId = null;
        $this->detailSsl = null;
        $this->detailSecurityLevel = null;
    }

    public function setSslMode(string $mode): void
    {
        Gate::authorize('cloudflare.ssl.manage');

        $zone = $this->detail;
        if (! $zone) return;

        if ($this->cloudflare->setSslMode($zone->zone_id, $mode)) {
            $zone->update(['ssl_mode' => $mode]);
            $this->detailSsl = $mode;
            $this->toastSuccess("SSL mode diubah ke {$mode}");
        } else {
            $this->toastError('Gagal mengubah SSL mode');
        }
    }

    public function setSecurityLevel(string $level): void
    {
        Gate::authorize('cloudflare.ddos.manage');

        $zone = $this->detail;
        if (! $zone) return;

        if ($this->cloudflare->setSecurityLevel($zone->zone_id, $level)) {
            $zone->update(['security_level' => $level]);
            $this->detailSecurityLevel = $level;
            $label = $level === 'under_attack' ? 'Under Attack Mode AKTIF' : 'Security level diubah ke '.$level;
            $this->toastSuccess($label);
        } else {
            $this->toastError('Gagal mengubah security level');
        }
    }

    public function openPurge(string $zoneId, string $zoneName): void
    {
        $this->purgeZoneId = $zoneId;
        $this->purgeZoneName = $zoneName;
        $this->purgeType = 'all';
        $this->purgeUrls = '';
        $this->dispatch('modal-open:zone-purge');
    }

    public function doPurge(): void
    {
        Gate::authorize('cloudflare.cache.purge');

        $success = false;

        if ($this->purgeType === 'all') {
            $success = $this->cloudflare->purgeAllCache($this->purgeZoneId);
        } else {
            $urls = array_filter(array_map('trim', explode("\n", $this->purgeUrls)));
            if (empty($urls)) {
                $this->toastError('Masukkan minimal 1 URL');
                return;
            }
            $success = $this->cloudflare->purgeUrls($this->purgeZoneId, $urls);
        }

        if ($success) {
            $this->toastSuccess("Cache {$this->purgeZoneName} berhasil di-purge");
            $this->dispatch('modal-close:zone-purge');
        } else {
            $this->toastError('Gagal purge cache');
        }
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

    public function syncRegistry(ZoneRegistrySync $sync): void
    {
        Gate::authorize('cloudflare.zone.view');

        $stats = $sync->sync();
        unset($this->assetMap);

        $parts = [];
        if ($stats['created']) $parts[] = "{$stats['created']} baru";
        if ($stats['linked']) $parts[] = "{$stats['linked']} terhubung";
        if ($stats['updated']) $parts[] = "{$stats['updated']} diperbarui";
        if (! $parts) $parts[] = 'semua up-to-date';

        $this->toastSuccess('Sync registry: '.implode(', ', $parts)." (dari {$stats['total']} zone)");
    }

    public function render()
    {
        return view('nawasara-cloudflare::livewire.pages.zone.section.table');
    }
}
