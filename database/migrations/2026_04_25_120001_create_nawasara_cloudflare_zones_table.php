<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nawasara_cloudflare_zones', function (Blueprint $table) {
            $table->id();

            $table->string('zone_id', 64)->unique();        // Cloudflare zone UUID
            $table->string('name', 255)->index();           // 'kominfo.go.id'
            $table->string('status', 32)->nullable();       // 'active', 'pending', 'moved', 'deleted'
            $table->string('type', 32)->nullable();         // 'full', 'partial', 'secondary'
            $table->string('plan_name', 64)->nullable();    // 'Free Website', etc.

            // Settings cache (rarely changes)
            $table->string('ssl_mode', 32)->nullable();     // 'off', 'flexible', 'full', 'strict'
            $table->string('security_level', 32)->nullable(); // 'low', 'medium', 'high', 'under_attack'
            $table->boolean('always_use_https')->default(false);
            $table->boolean('development_mode')->default(false);

            // Name servers
            $table->json('name_servers')->nullable();
            $table->json('original_name_servers')->nullable();

            // Stats
            $table->unsignedInteger('dns_records_count')->default(0);
            $table->timestamp('cf_created_at')->nullable();
            $table->timestamp('cf_modified_at')->nullable();

            // Sync tracking (HasSyncStatus trait)
            $table->string('sync_status', 32)->default('synced');
            $table->text('sync_error')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->string('content_hash', 64)->nullable();

            $table->timestamps();

            $table->index(['status', 'sync_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nawasara_cloudflare_zones');
    }
};
