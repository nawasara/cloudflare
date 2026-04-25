<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nawasara_cloudflare_dns_records', function (Blueprint $table) {
            $table->id();

            $table->string('record_id', 64)->unique();      // Cloudflare DNS record UUID
            $table->string('zone_id', 64)->index();         // FK ke zones.zone_id
            $table->string('zone_name', 255)->index();      // denormalized for fast filter

            $table->string('name', 255)->index();           // 'sub.example.com'
            $table->string('type', 16)->index();            // A, AAAA, CNAME, MX, TXT, NS, SRV, CAA
            $table->text('content');                        // record value
            $table->unsignedInteger('ttl')->default(1);     // 1 = automatic
            $table->boolean('proxied')->default(false);
            $table->unsignedInteger('priority')->nullable(); // for MX

            // Comments & tags (Cloudflare allows annotations)
            $table->text('comment')->nullable();
            $table->json('tags')->nullable();

            // Cloudflare timestamps
            $table->timestamp('cf_created_at')->nullable();
            $table->timestamp('cf_modified_at')->nullable();

            // Sync tracking
            $table->string('sync_status', 32)->default('synced');
            $table->text('sync_error')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->string('content_hash', 64)->nullable();

            $table->timestamps();

            $table->index(['zone_id', 'type']);
            $table->index(['zone_id', 'sync_status']);
            $table->index(['zone_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nawasara_cloudflare_dns_records');
    }
};
