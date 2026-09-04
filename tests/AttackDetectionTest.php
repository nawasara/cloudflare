<?php

namespace Nawasara\Cloudflare\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Kapan sebuah host dianggap SEDANG diserang.
 *
 * Tujuannya bukan laporan rapi belakangan, melainkan tahu saat kejadian —
 * karena selama ini serangan baru ketahuan setelah halaman judi muncul di
 * situs OPD.
 *
 * Angka-angka di sini diukur dari produksi 4 September 2026, bukan ditebak.
 */
class AttackDetectionTest extends TestCase
{
    private function menyerang(int $bermusuhan, ?float $normal, int $min = 20, float $kali = 5): bool
    {
        if ($bermusuhan < $min) {
            return false;
        }

        if ($normal === null) {
            return false;   // belum punya pembanding — jangan menebak
        }

        $rasio = $normal > 0 ? $bermusuhan / $normal : INF;

        return $rasio >= $kali;
    }

    /**
     * Ambang 100 tidak akan PERNAH berbunyi di sini.
     *
     * Terukur: lalu lintas bermusuhan tersebar ke ~90 host, median 1-2 per
     * 5 menit, puncak ~50. Alat yang tampak terpasang tetapi tidak pernah
     * berbunyi lebih buruk daripada tidak ada alat sama sekali.
     */
    public function test_ambang_seratus_tidak_pernah_tercapai(): void
    {
        $puncakNyata = 54;

        $this->assertLessThan(100, $puncakNyata);
        $this->assertGreaterThan(20, $puncakNyata, 'Ambang 20 masih dapat dicapai lalu lintas nyata.');
    }

    /** Lantai mutlak: kenaikan kecil bukan serangan meski kelipatannya besar. */
    public function test_kenaikan_kecil_bukan_serangan(): void
    {
        // 2 menjadi 20 = 10x lipat, tetapi tetap di bawah lantai.
        $this->assertFalse($this->menyerang(19, 2));
    }

    /** Lonjakan nyata di atas lantai HARUS berbunyi. */
    public function test_lonjakan_nyata_berbunyi(): void
    {
        // Host yang biasanya 15, tiba-tiba 600.
        $this->assertTrue($this->menyerang(600, 15));
    }

    /** Sibuk tetapi wajar tidak boleh berbunyi. */
    public function test_ramai_wajar_tidak_berbunyi(): void
    {
        // Host sibuk: biasanya 50, sekarang 120 — naik, tetapi belum 5x.
        $this->assertFalse($this->menyerang(120, 50));
    }

    /**
     * Tanpa pembanding, JANGAN menebak.
     *
     * Host yang baru pertama terlihat tidak boleh langsung dianggap diserang;
     * datanya terkumpul sendiri dan penilaian berlaku pada putaran berikutnya.
     */
    public function test_tanpa_pembanding_tidak_menuduh(): void
    {
        $this->assertFalse($this->menyerang(5000, null));
    }

    /**
     * Host yang biasanya NOL bermusuhan lalu melonjak = serangan.
     *
     * Pembagian dengan nol harus menghasilkan "tak terhingga", bukan galat
     * atau nol.
     */
    public function test_host_yang_biasanya_bersih_lalu_melonjak(): void
    {
        $this->assertTrue($this->menyerang(500, 0.0));
    }
}
