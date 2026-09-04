<?php

namespace Nawasara\Cloudflare\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Cuplikan lalu lintas WAF satu host pada satu jendela waktu.
 *
 * Ada semata-mata sebagai PEMBANDING: Cloudflare hanya melayani kueri 24 jam
 * ke belakang, sedangkan untuk membedakan serangan dari jam sibuk kita perlu
 * jendela yang sama minggu lalu.
 */
class AttackWindow extends Model
{
    protected $table = 'nawasara_cloudflare_attack_windows';

    protected $fillable = [
        'host', 'window_start', 'window_minutes',
        'blocked', 'challenged', 'allowed', 'total',
        'top_country', 'top_country_count',
    ];

    protected $casts = [
        'window_start' => 'datetime',
        'blocked' => 'integer',
        'challenged' => 'integer',
        'allowed' => 'integer',
        'total' => 'integer',
        'top_country_count' => 'integer',
    ];

    /**
     * Yang dianggap "bermusuhan" — bukan seluruh lalu lintas.
     *
     * `skip` sengaja TIDAK dihitung meski jumlahnya paling besar (193 ribu
     * dalam 6 jam): ia berarti lalu lintas yang DILEWATKAN sebuah aturan, dan
     * besar dalam keadaan normal. Menghitungnya membuat setiap jam sibuk
     * terlihat seperti serangan.
     */
    public function getHostileAttribute(): int
    {
        return $this->blocked + $this->challenged;
    }
}
