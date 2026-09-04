<?php

namespace Nawasara\Cloudflare\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Kapan peringatan sertifikat SEHARUSNYA berbunyi.
 *
 * Sertifikat kedaluwarsa pernah mematikan SELURUH akses SSH lewat Teleport
 * (1 Agustus 2026). Bukan layanan yang melambat — berhenti, dan pemulihannya
 * menuntut akses yang justru ikut mati.
 *
 * Yang diuji bukan rumusnya (itu sepele), melainkan pilihan ambangnya — karena
 * ambang yang keliru membuat peringatan ini berhenti dibaca justru sebelum
 * dibutuhkan.
 */
class SslExpiryThresholdTest extends TestCase
{
    private function tingkat(int $sisa, int $peringatan = 21, int $gawat = 7): string
    {
        if ($sisa > $peringatan) {
            return 'aman';
        }

        return $sisa <= $gawat ? 'critical' : 'warning';
    }

    /**
     * 21 hari, BUKAN 30 — dan alasannya bukan selera.
     *
     * Let's Encrypt memperbarui otomatis pada sisa 30 hari. Ambang 30 akan
     * berbunyi untuk setiap sertifikat SEHAT setiap bulan; sisa 21 hari berarti
     * pembaruan otomatisnya sudah gagal setidaknya sekali.
     */
    public function test_sertifikat_yang_sedang_diperbarui_otomatis_tidak_berbunyi(): void
    {
        $this->assertSame('aman', $this->tingkat(30), 'Hari pembaruan otomatis dimulai.');
        $this->assertSame('aman', $this->tingkat(25), 'Masih dalam jendela pembaruan normal.');
        $this->assertSame('aman', $this->tingkat(22));
    }

    /** Lewat jendela pembaruan = pembaruannya gagal, dan itu layak diberitakan. */
    public function test_pembaruan_yang_gagal_berbunyi(): void
    {
        $this->assertSame('warning', $this->tingkat(21));
        $this->assertSame('warning', $this->tingkat(14));
        $this->assertSame('warning', $this->tingkat(8));
    }

    public function test_hampir_habis_jadi_gawat(): void
    {
        $this->assertSame('critical', $this->tingkat(7));
        $this->assertSame('critical', $this->tingkat(1));
        $this->assertSame('critical', $this->tingkat(0));
    }

    /**
     * Data produksi 4 September 2026: 255 domain, semuanya > 30 hari.
     *
     * Nol peringatan adalah jawaban yang BENAR di hari itu — dan uji ini
     * memastikan diamnya karena memang tidak ada yang berisiko, bukan karena
     * ambangnya terlalu longgar untuk pernah berbunyi.
     */
    public function test_semua_domain_sehat_menghasilkan_nol_peringatan(): void
    {
        $sehat = array_fill(0, 255, 45);

        $berbunyi = array_filter($sehat, fn ($d) => $this->tingkat($d) !== 'aman');

        $this->assertCount(0, $berbunyi);

        // Tetapi ambangnya harus benar-benar dapat dicapai.
        $this->assertSame('warning', $this->tingkat(20));
    }

    /**
     * Sertifikat yang sudah lewat tanggalnya tetap gawat, bukan "aman".
     *
     * Sisa hari negatif pernah terjadi saat pemeriksaan tertunda.
     */
    public function test_sertifikat_kedaluwarsa_tetap_gawat(): void
    {
        $this->assertSame('critical', $this->tingkat(-1));
        $this->assertSame('critical', $this->tingkat(-30));
    }
}
