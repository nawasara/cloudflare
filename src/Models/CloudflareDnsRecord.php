<?php

namespace Nawasara\Cloudflare\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Nawasara\Sync\Concerns\HasSyncStatus;

/**
 * Snapshot of Cloudflare DNS records.
 */
class CloudflareDnsRecord extends Model
{
    use HasSyncStatus;

    protected $table = 'nawasara_cloudflare_dns_records';

    protected $fillable = [
        'record_id', 'zone_id', 'zone_name',
        'name', 'type', 'content', 'ttl', 'proxied', 'priority',
        'comment', 'tags',
        'cf_created_at', 'cf_modified_at',
        'sync_status', 'sync_error', 'last_synced_at',
        'content_hash',
    ];

    protected $casts = [
        'ttl' => 'integer',
        'proxied' => 'boolean',
        'priority' => 'integer',
        'tags' => 'array',
        'cf_created_at' => 'datetime',
        'cf_modified_at' => 'datetime',
        'last_synced_at' => 'datetime',
    ];

    public function zone(): BelongsTo
    {
        return $this->belongsTo(CloudflareZone::class, 'zone_id', 'zone_id');
    }

    public function computeContentHash(): string
    {
        return hash('sha256', json_encode([
            'name' => $this->name,
            'type' => $this->type,
            'content' => $this->content,
            'ttl' => $this->ttl,
            'proxied' => $this->proxied,
            'priority' => $this->priority,
        ]));
    }

    public function scopeForZone($query, ?string $zoneId)
    {
        return $zoneId ? $query->where('zone_id', $zoneId) : $query;
    }

    public function scopeSearch($query, ?string $term)
    {
        if (! $term) return $query;
        $term = '%'.$term.'%';
        return $query->where(function ($q) use ($term) {
            $q->where('name', 'like', $term)
                ->orWhere('content', 'like', $term);
        });
    }

    /**
     * Filter by record type. Accepts a scalar string for back-compat or an
     * array for multi-select (e.g. ['A', 'AAAA', 'CNAME']).
     */
    public function scopeType($query, string|array|null $type)
    {
        if ($type === null || $type === '' || (is_array($type) && empty($type))) {
            return $query;
        }
        return is_array($type)
            ? $query->whereIn('type', $type)
            : $query->where('type', $type);
    }

    public function scopeProxied($query, ?bool $proxied)
    {
        if ($proxied === null) return $query;
        return $query->where('proxied', $proxied);
    }
}
