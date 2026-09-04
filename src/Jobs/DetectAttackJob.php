<?php

namespace Nawasara\Cloudflare\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Nawasara\Alerting\Facades\Alerter;
use Nawasara\Alerting\Models\AlertRule;
use Nawasara\Cloudflare\Models\AttackWindow;
use Nawasara\Cloudflare\Models\CloudflareZone;
use Nawasara\Cloudflare\Services\CloudflareClient;

/**
 * Memberi tahu SAAT sebuah situs sedang diserang.
 *
 * Selama ini serangan baru diketahui setelah akibatnya terlihat — halaman
 * judi yang tiba-tiba muncul di situs OPD. Padahal Cloudflare sudah menahan
 * ~136.000 permintaan bermusuhan setiap hari, dan tak satu pun pernah sampai
 * ke Nawasara. Kita hanya melihat yang LOLOS, tanpa pernah tahu berapa yang
 * tertahan atau kapan tekanannya meningkat.
 *
 * Yang dijawab job ini bukan "apakah pernah ada serangan", melainkan
 * "apakah SEKARANG ada situs yang sedang dihantam" — dan situs yang mana.
 *
 * Per HOST, karena satu zona memayungi puluhan situs OPD. "Ada serangan"
 * tidak dapat ditindaklanjuti; nama hostnya langsung menunjuk siapa yang
 * harus dihubungi.
 *
 * Dinilai dengan PEMBANDING, bukan ambang mutlak. Ambang tetap salah dua
 * arah sekaligus: situs besar berbunyi tiap hari, situs kecil tak pernah
 * berbunyi meski sedang dihantam.
 *
 * Pembandingnya jam yang SAMA, bukan jam sebelumnya — trafik pemerintahan
 * turun drastis malam hari, dan membandingkan 08:00 dengan 03:00 membuat
 * setiap pagi terlihat seperti serangan.
 */
class DetectAttackJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public function handle(CloudflareClient $client): void
    {
        if (! config('nawasara-cloudflare.attack_detection.enabled', true)) {
            return;
        }

        if (! $client->isConfigured()) {
            return;
        }

        $menit = (int) config('nawasara-cloudflare.attack_detection.window_minutes', 5);

        // Jendela ditutup satu menit ke belakang: kejadian paling akhir masih
        // dalam perjalanan ke Cloudflare, dan menghitungnya membuat jendela
        // terbaru selalu tampak lebih sepi daripada seharusnya.
        $until = now()->subMinute()->startOfMinute();
        $since = $until->copy()->subMinutes($menit);

        foreach (CloudflareZone::query()->where('status', 'active')->get() as $zone) {
            $this->periksaZona($client, $zone, $since, $until, $menit);
        }
    }

    protected function periksaZona(CloudflareClient $client, CloudflareZone $zone, $since, $until, int $menit): void
    {
        $events = $client->getFirewallEvents($zone->zone_id, $since, $until);

        if ($events === []) {
            return;
        }

        $perHost = [];

        foreach ($events as $e) {
            $h = $e['host'];
            $perHost[$h] ??= ['blocked' => 0, 'challenged' => 0, 'allowed' => 0, 'total' => 0, 'negara' => []];

            $perHost[$h]['total'] += $e['count'];

            // `skip` sengaja tidak dihitung sebagai bermusuhan meski jumlahnya
            // paling besar: ia berarti lalu lintas yang DILEWATKAN sebuah aturan,
            // dan besar dalam keadaan normal.
            match ($e['action']) {
                'block', 'drop' => $perHost[$h]['blocked'] += $e['count'],
                'managed_challenge', 'challenge', 'jschallenge' => $perHost[$h]['challenged'] += $e['count'],
                'allow' => $perHost[$h]['allowed'] += $e['count'],
                default => null,
            };

            if ($e['country']) {
                $perHost[$h]['negara'][$e['country']] = ($perHost[$h]['negara'][$e['country']] ?? 0) + $e['count'];
            }
        }

        foreach ($perHost as $host => $d) {
            arsort($d['negara']);
            $topNegara = array_key_first($d['negara']) ?: null;

            $window = AttackWindow::updateOrCreate(
                ['host' => $host, 'window_start' => $since],
                [
                    'window_minutes' => $menit,
                    'blocked' => $d['blocked'],
                    'challenged' => $d['challenged'],
                    'allowed' => $d['allowed'],
                    'total' => $d['total'],
                    'top_country' => $topNegara,
                    'top_country_count' => $topNegara ? $d['negara'][$topNegara] : 0,
                ],
            );

            $this->nilai($window);
        }
    }

    /**
     * Apakah jendela ini menandakan serangan yang sedang berlangsung?
     */
    protected function nilai(AttackWindow $w): void
    {
        if (! class_exists(Alerter::class)) {
            return;
        }

        $this->registerRules();

        $alerter = Alerter::class;
        $key = 'cloudflare.attack.surge';
        $target = $w->host;

        $bermusuhan = $w->hostile;
        $minimum = (int) config('nawasara-cloudflare.attack_detection.min_hostile', 100);
        $kelipatan = (float) config('nawasara-cloudflare.attack_detection.spike_multiplier', 5);

        // Lantai mutlak: tanpa ini, host yang biasanya 2 permintaan bermusuhan
        // lalu menjadi 20 akan berbunyi sebagai lonjakan 10 kali lipat — benar
        // secara hitungan, tetapi bukan serangan.
        if ($bermusuhan < $minimum) {
            $alerter::resolve($key, 'CloudflareHost', $target);

            return;
        }

        $normal = $this->normalUntuk($w);

        // Belum punya pembanding: jangan menebak. Data terkumpul sendiri dan
        // penilaiannya mulai berlaku pada putaran berikutnya.
        if ($normal === null) {
            return;
        }

        $rasio = $normal > 0 ? $bermusuhan / $normal : INF;

        if ($rasio < $kelipatan) {
            $alerter::resolve($key, 'CloudflareHost', $target);

            return;
        }

        $alerter::fire($key, 'CloudflareHost', $target, [
            'label' => $w->host,
            'diblokir' => $w->blocked,
            'ditantang' => $w->challenged,
            'bermusuhan' => $bermusuhan,
            'biasanya' => round($normal),
            'lonjakan' => is_finite($rasio) ? round($rasio, 1).'x' : 'baru',
            'negara_terbanyak' => $w->top_country,
            'jendela' => $w->window_minutes.' menit',
        ]);
    }

    /**
     * Berapa yang WAJAR untuk host ini pada jam seperti ini?
     *
     * Diambil dari jendela pada JAM YANG SAMA selama 7 hari terakhir. Bila
     * riwayatnya belum cukup, dipakai rata-rata 24 jam terakhir host itu —
     * masih jauh lebih baik daripada ambang tetap, dan membuat deteksi mulai
     * bekerja pada hari pertama alih-alih menunggu seminggu.
     */
    protected function normalUntuk(AttackWindow $w): ?float
    {
        $jam = $w->window_start->hour;

        $mingguan = DB::table('nawasara_cloudflare_attack_windows')
            ->where('host', $w->host)
            ->where('id', '!=', $w->id)
            ->where('window_start', '>=', now()->subDays(7))
            ->whereRaw('HOUR(window_start) = ?', [$jam])
            ->selectRaw('AVG(blocked + challenged) rata, COUNT(*) n')
            ->first();

        if ($mingguan && $mingguan->n >= 3) {
            return (float) $mingguan->rata;
        }

        $harian = DB::table('nawasara_cloudflare_attack_windows')
            ->where('host', $w->host)
            ->where('id', '!=', $w->id)
            ->where('window_start', '>=', now()->subDay())
            ->selectRaw('AVG(blocked + challenged) rata, COUNT(*) n')
            ->first();

        return ($harian && $harian->n >= 3) ? (float) $harian->rata : null;
    }

    protected function registerRules(): void
    {
        $alerter = Alerter::class;

        if ($alerter::hasRule('cloudflare.attack.surge')) {
            return;
        }

        $alerter::registerRule(AlertRule::make([
            'key' => 'cloudflare.attack.surge',
            'severity' => 'critical',
            'category' => 'keamanan',

            // Tiga puluh menit: cukup jarang agar satu serangan panjang tidak
            // mengirim belasan pesan, cukup sering agar tekanan yang belum reda
            // tetap terasa.
            'cooldown_minutes' => (int) config('nawasara-cloudflare.attack_detection.cooldown_minutes', 30),
            'description' => 'Lonjakan lalu lintas bermusuhan di Cloudflare',
            'subject_template' => '{context.label} sedang dihantam - {context.bermusuhan} permintaan ditahan ({context.lonjakan} dari biasanya)',
        ]));
    }
}
