<?php

namespace Nawasara\Cloudflare\Search;

use Nawasara\Cloudflare\Models\CloudflareZone;
use Nawasara\Search\Contracts\SearchProvider;

class CloudflareZoneSearchProvider implements SearchProvider
{
    public function key(): string
    {
        return 'cloudflare-zone';
    }

    public function label(): string
    {
        return 'Cloudflare';
    }

    public function permission(): ?string
    {
        return 'cloudflare.zone.view';
    }

    public function search(string $term, int $limit): array
    {
        return CloudflareZone::query()
            ->search($term)
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'name'])
            ->map(fn (CloudflareZone $z) => [
                'label' => $z->name,
                'sublabel' => 'Zone',
                'url' => url('nawasara-cloudflare/zones?search='.urlencode($term)),
            ])
            ->all();
    }
}
