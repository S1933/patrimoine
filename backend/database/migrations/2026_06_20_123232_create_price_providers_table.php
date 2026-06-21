<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_providers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 40)->unique();
            $table->string('label', 80);
            $table->smallInteger('supported_types')->unsigned();
            $table->string('base_url', 255)->nullable();
            // Name of the env variable holding the API key (never the key itself).
            $table->string('api_key_env', 100)->nullable();
            $table->integer('rate_limit_per_min')->default(60);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('priority')->default(100);
            $table->timestampsTz();
            $table->index(['is_active', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_providers');
    }
};
