<?php

namespace Nawasara\Cloudflare\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Nawasara\Alerting\Facades\Alerter;
use Nawasara\Alerting\Models\AlertRule;
use Nawasara\Cloudflare\Models\EndpointHealth;

/**
 * Memperingatkan sertifikat TLS yang akan kedaluwarsa.
 *
 * ## Kenapa dari sini, bukan dari agen
 *
 * Agen punya plugin SSL, tetapi ia hanya memeriksa hostname yang dikonfigurasi
 * pada mesin tempat agen itu berjalan. Per 4 September 2026 plugin itu aktif di
 * 2 dari 6 agen, dan **tak satu pun punya daftar hostname** — sehingga nol
 * sertifikat benar-benar diperiksa.
 *
 * Cloudflare tahu SELURUH domainnya. `DnsHealthChecker` sudah memeriksa
 * sertifikat tiap malam dan menyimpan `ssl_days_remaining` untuk **255
 * domain** — datanya sudah ada, lengkap, dan tidak bergantung pada satu mesin
 * pun tetap hidup. Yang tidak pernah ada hanyalah pembacanya.
 *
 * Ini juga lebih tahan: domain yang mesinnya mati justru yang paling mungkin
 * terlewat oleh pemantauan berbasis agen, sementara di sini ia tetap terlihat.
 *
 * ## Kenapa ini penting
 *
 * Sertifikat kedaluwarsa pernah mematikan SELURUH akses SSH lewat Teleport
 * (1 Agustus 2026). Bukan layanan yang melambat — berhenti, dan pemulihannya
 * menuntut akses yang justru ikut mati.
 *
 * ## Ambangnya
 *
 * Let's Encrypt memperbarui otomatis pada sisa 30 hari. Ambang **21 hari**
 * karena itu bukan sekadar "hampir habis" — ia berarti **pembaruan otomatisnya
 * sudah gagal setidaknya sekali**, dan itu yang layak diberitakan. Ambang 30
 * hari akan berbunyi untuk setiap sertifikat sehat setiap bulan.
 */
class CheckSslExpiryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public function handle(): void
    {
        if (! class_exists(Alerter::class)) {
            return;
        }

        if (! config('nawasara-cloudflare.ssl_alerts.enabled', true)) {
            return;
        }

        $this->registerRules();

        $peringatan = (int) config('nawasara-cloudflare.ssl_alerts.warning_days', 21);
        $gawat = (int) config('nawasara-cloudflare.ssl_alerts.critical_days', 7);
        $alerter = Alerter::class;

        $berbunyi = 0;

        foreach (EndpointHealth::query()->whereNotNull('ssl_days_remaining')->get() as $ep) {
            $sisa = (int) $ep->ssl_days_remaining;
            $target = $ep->identifier;

            if ($sisa > $peringatan) {
                // Diperbarui — padamkan KEDUA tingkat. Memadamkan yang sedang
                // menyala saja meninggalkan sisa: domain yang sempat 5 hari
                // lalu diperbarui akan tetap menyala di tingkat gawat.
                $alerter::resolve('cloudflare.ssl.warning', 'Endpoint', $target);
                $alerter::resolve('cloudflare.ssl.critical', 'Endpoint', $target);

                continue;
            }

            $berbunyi++;

            $konteks = [
                'label' => $ep->identifier,
                'sisa_hari' => $sisa,
                'kedaluwarsa' => $ep->ssl_valid_to,
                'penerbit' => $ep->ssl_issuer,
            ];

            // Severity melekat pada ATURAN, bukan konteks — jadi tingkat gawat
            // harus jadi aturan sendiri, dan yang satunya dipadamkan supaya
            // satu domain tak pernah menyalakan dua peringatan.
            if ($sisa <= $gawat) {
                $alerter::resolve('cloudflare.ssl.warning', 'Endpoint', $target);
                $alerter::fire('cloudflare.ssl.critical', 'Endpoint', $target, $konteks);
            } else {
                $alerter::resolve('cloudflare.ssl.critical', 'Endpoint', $target);
                $alerter::fire('cloudflare.ssl.warning', 'Endpoint', $target, $konteks);
            }
        }

        if ($berbunyi > 0) {
            Log::info("[cloudflare] {$berbunyi} sertifikat mendekati kedaluwarsa");
        }
    }

    protected function registerRules(): void
    {
        $alerter = Alerter::class;

        $daftar = [
            'cloudflare.ssl.warning' => ['Sertifikat TLS mendekati kedaluwarsa', 'warning'],
            'cloudflare.ssl.critical' => ['Sertifikat TLS hampir kedaluwarsa', 'critical'],
        ];

        foreach ($daftar as $key => [$desc, $severity]) {
            if ($alerter::hasRule($key)) {
                continue;
            }

            $alerter::registerRule(AlertRule::make([
                'key' => $key,
                'severity' => $severity,
                'category' => 'sertifikat',

                // Sekali sehari. Sertifikat tidak berubah keadaannya tiap jam,
                // dan mengingatkan lebih sering hanya menenggelamkan domain
                // lain yang baru masuk ambang.
                'cooldown_minutes' => 1440,
                'description' => $desc,
                'subject_template' => 'Sertifikat {context.label} tersisa {context.sisa_hari} hari',
            ]));
        }
    }
}
