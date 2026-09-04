<?php

return [
    // DNS records per page
    'per_page' => 25,

    // Cache zone list TTL in seconds
    'cache_ttl' => 300,

    /*
    |--------------------------------------------------------------------------
    | Peringatan sertifikat TLS
    |--------------------------------------------------------------------------
    |
    | Dibaca dari EndpointHealth yang sudah diisi `cloudflare:health-check-ssl`
    | tiap malam — 255 domain, tanpa bergantung pada agen mana pun tetap hidup.
    |
    | 21 hari, BUKAN 30: Let's Encrypt memperbarui otomatis pada sisa 30 hari,
    | jadi ambang 30 akan berbunyi untuk setiap sertifikat sehat setiap bulan.
    | Sisa 21 hari berarti pembaruan otomatisnya sudah gagal setidaknya sekali —
    | dan itu yang layak diberitakan.
    |
    | Sertifikat kedaluwarsa pernah mematikan seluruh akses SSH lewat Teleport
    | pada 1 Agustus 2026. Bukan melambat — berhenti, dan pemulihannya menuntut
    | akses yang justru ikut mati.
    |
    */
    'ssl_alerts' => [
        'enabled' => env('CLOUDFLARE_SSL_ALERTS_ENABLED', true),
        'warning_days' => (int) env('CLOUDFLARE_SSL_WARNING_DAYS', 21),
        'critical_days' => (int) env('CLOUDFLARE_SSL_CRITICAL_DAYS', 7),
        'timezone' => env('CLOUDFLARE_SSL_TIMEZONE', 'Asia/Jakarta'),
    ],

];
