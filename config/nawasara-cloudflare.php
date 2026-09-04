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

    /*
    |--------------------------------------------------------------------------
    | Deteksi serangan yang SEDANG berlangsung
    |--------------------------------------------------------------------------
    |
    | Selama ini serangan baru diketahui setelah akibatnya terlihat — halaman
    | judi yang muncul di situs OPD. Cloudflare sudah menahan ~136.000
    | permintaan bermusuhan tiap hari, dan tak satu pun sampai ke Nawasara.
    |
    | Diperiksa tiap 2 menit dengan jendela 5 menit: cukup sering untuk tahu
    | saat kejadian, cukup lebar agar lonjakan sesaat tidak terbaca sebagai
    | serangan.
    |
    | `min_hostile` adalah LANTAI mutlak. Tanpa itu, host yang biasanya 2
    | permintaan bermusuhan lalu menjadi 20 berbunyi sebagai lonjakan 10 kali
    | lipat — benar secara hitungan, tetapi bukan serangan.
    |
    | `spike_multiplier` dibandingkan dengan kebiasaan host itu sendiri pada
    | JAM YANG SAMA, bukan angka tetap: ambang tetap salah dua arah sekaligus,
    | situs besar berbunyi tiap hari dan situs kecil tak pernah berbunyi.
    |
    */
    'attack_detection' => [
        'enabled' => env('CLOUDFLARE_ATTACK_DETECTION', true),
        'window_minutes' => (int) env('CLOUDFLARE_ATTACK_WINDOW', 5),
        // 20, BUKAN 100. Diukur di produksi 4 September 2026: lalu lintas
        // bermusuhan tersebar ke ~90 host dengan MEDIAN 1-2 per 5 menit dan
        // puncak sekitar 50. Ambang 100 tidak akan pernah tercapai — ia akan
        // tampak terpasang sambil tidak pernah berbunyi, dan itu lebih buruk
        // daripada tidak ada deteksi sama sekali.
        'min_hostile' => (int) env('CLOUDFLARE_ATTACK_MIN_HOSTILE', 20),
        'spike_multiplier' => (float) env('CLOUDFLARE_ATTACK_SPIKE', 5),
        'cooldown_minutes' => (int) env('CLOUDFLARE_ATTACK_COOLDOWN', 30),

        // Cuplikan disimpan hanya sebagai pembanding. Cloudflare sendiri
        // menolak kueri lebih dari 24 jam, jadi riwayat mingguan harus ada di
        // sini — tetapi tidak perlu lebih lama dari itu.
        'retention_days' => (int) env('CLOUDFLARE_ATTACK_RETENTION_DAYS', 30),
    ],

];
