<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nawasara_cloudflare_endpoint_health', function (Blueprint $table) {
            $table->id();
            $table->string('identifier')->unique();
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->string('error', 200)->nullable();
            $table->integer('ssl_days_remaining')->nullable();
            $table->timestamp('ssl_valid_to')->nullable();
            $table->string('ssl_issuer', 200)->nullable();
            $table->string('ssl_cn', 200)->nullable();
            $table->string('ssl_error', 200)->nullable();
            $table->string('state', 16)->default('unchecked')->index();
            $table->timestamp('checked_at')->nullable()->index();
            $table->timestamp('ssl_checked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nawasara_cloudflare_endpoint_health');
    }
};
