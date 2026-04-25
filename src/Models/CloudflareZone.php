<?php

namespace Nawasara\Cloudflare\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Nawasara\Sync\Concerns\HasSyncStatus;

/**
 * Snapshot of Cloudflare zones (domains).
 */
class CloudflareZone extends Model
{
    use HasSyncStatus;

    protected $table = 'nawasara_cloudflare_zones';

    protected $fillable = [
        'zone_id', 'name', 'status', 'type', 'plan_name',
        'ssl_mode', 'security_level', 'always_use_https', 'development_mode',
        'name_servers', 'original_name_servers',
        'dns_records_count',
        'cf_created_at', 'cf_modified_at',
        'sync_status', 'sync_error', 'last_synced_at',
        'content_hash',
    ];

    protected $casts = [
        'always_use_https' => 'boolean',
        'development_mode' => 'boolean',
        'name_servers' => 'array',
        'original_name_servers' => 'array',
        'dns_records_count' => 'integer',
        'cf_created_at' => 'datetime',
        'cf_modified_at' => 'datetime',
        'last_synced_at' => 'datetime',
    ];

    public function dnsRecords(): HasMany
    {
        return $this->hasMany(CloudflareDnsRecord::class, 'zone_id', 'zone_id');
    }

    public function computeContentHash(): string
    {
        return hash('sha256', json_encode([
            'status' => $this->status,
            'ssl_mode' => $this->ssl_mode,
            'security_level' => $this->security_level,
            'always_use_https' => $this->always_use_https,
            'development_mode' => $this->development_mode,
        ]));
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function scopeSearch($query, ?string $term)
    {
        if (! $term) return $query;
        $term = '%'.$term.'%';
        return $query->where('name', 'like', $term);
    }

    public function scopeStatus($query, ?string $status)
    {
        return $status ? $query->where('status', $status) : $query;
    }
}
