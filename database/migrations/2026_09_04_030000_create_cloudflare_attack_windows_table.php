<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cuplikan lalu lintas WAF per host, per jendela waktu.
 *
 * ## Kenapa disimpan sendiri
 *
 * Cloudflare hanya melayani kueri **maksimal 24 jam** ke belakang (dicoba:
 * rentang 3 hari ditolak). Padahal untuk tahu sebuah lonjakan itu serangan
 * atau memang jam sibuk, kita perlu pembanding **minggu lalu pada jam yang
 * sama** — trafik pemerintahan turun drastis malam hari, dan membandingkannya
 * dengan satu jam sebelumnya membuat tiap pagi terlihat seperti serangan.
 *
 * Jadi cuplikannya disimpan di sini, dan CF hanya dipakai untuk jendela
 * terbaru.
 *
 * ## Kenapa per HOST, bukan per zona
 *
 * "Ada serangan" tidak dapat ditindaklanjuti. "`simashebat.ponorogo.go.id`
 * sedang dihantam" langsung menunjuk siapa yang harus dihubungi — dan satu
 * zona di sini memayungi puluhan situs OPD.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nawasara_cloudflare_attack_windows', function (Blueprint $table) {
            $table->id();

            $table->string('host', 191)->index();

            // Awal jendela, dibulatkan ke kelipatan menit jendela. Dipakai
            // sebagai kunci dedup: satu host satu jendela satu baris.
            $table->timestamp('window_start')->index();
            $table->unsignedSmallInteger('window_minutes')->default(5);

            // Dipisah per aksi karena artinya berbeda: `block` berarti WAF
            // menahan sesuatu, `skip` justru lalu lintas yang DILEWATKAN
            // aturan — ia besar dalam keadaan normal dan bukan tanda bahaya.
            $table->unsignedInteger('blocked')->default(0);
            $table->unsignedInteger('challenged')->default(0);
            $table->unsignedInteger('allowed')->default(0);
            $table->unsignedInteger('total')->default(0);

            // Negara terbanyak pada jendela ini — perubahan mendadak di sini
            // sering menjadi tanda paling awal.
            $table->string('top_country', 8)->nullable();
            $table->unsignedInteger('top_country_count')->default(0);

            $table->timestamps();

            $table->unique(['host', 'window_start'], 'cf_attack_host_window_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nawasara_cloudflare_attack_windows');
    }
};
