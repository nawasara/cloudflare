<?php

namespace Nawasara\Cloudflare\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Nawasara\Registry\Models\Asset;

class EndpointHealth extends Model
{
    protected $table = 'nawasara_cloudflare_endpoint_health';

    protected $fillable = [
        'identifier',
        'status_code',
        'response_time_ms',
        'error',
        'ssl_days_remaining',
        'ssl_valid_to',
        'ssl_issuer',
        'ssl_cn',
        'ssl_error',
        'state',
        'checked_at',
        'ssl_checked_at',
    ];

    protected $casts = [
        'checked_at' => 'datetime',
        'ssl_checked_at' => 'datetime',
        'ssl_valid_to' => 'datetime',
        'status_code' => 'integer',
        'response_time_ms' => 'integer',
        'ssl_days_remaining' => 'integer',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'identifier', 'identifier')
            ->where('package_ref', 'cloudflare')
            ->where('type', 'subdomain');
    }

    public function scopeState($query, ?string $state)
    {
        if ($state) {
            $query->where('state', $state);
        }
        return $query;
    }
}
