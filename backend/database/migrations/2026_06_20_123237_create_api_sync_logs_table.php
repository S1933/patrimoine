<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_sync_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('run_id')->index();
            $table->uuid('provider_id')->nullable();
            $table->uuid('investment_id')->nullable();
            // success | error | fallback | skipped
            $table->string('status', 20);
            $table->unsignedInteger('duration_ms')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->foreign('provider_id')->references('id')->on('price_providers')->nullOnDelete();
            $table->foreign('investment_id')->references('id')->on('investments')->cascadeOnDelete();

            $table->index('created_at');
            $table->index(['provider_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_sync_logs');
    }
};
