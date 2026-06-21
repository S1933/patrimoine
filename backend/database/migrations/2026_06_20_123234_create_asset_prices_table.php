<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_prices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('investment_id');
            $table->uuid('provider_id')->nullable();
            $table->decimal('price', 18, 6);
            $table->char('currency', 3);
            $table->timestampTz('fetched_at')->useCurrent();
            // success | fallback | error | manual
            $table->string('source_status', 20)->default('success');
            $table->text('error_message')->nullable();
            $table->jsonb('raw_payload')->nullable();

            $table->foreign('investment_id')->references('id')->on('investments')->cascadeOnDelete();
            $table->foreign('provider_id')->references('id')->on('price_providers')->nullOnDelete();

            $table->index(['investment_id', 'fetched_at']);
            $table->index('fetched_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_prices');
    }
};
