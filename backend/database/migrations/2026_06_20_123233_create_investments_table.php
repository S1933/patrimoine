<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->unsignedSmallInteger('asset_type_id');
            $table->string('name', 120);
            $table->string('isin', 12)->nullable();
            $table->string('symbol', 40)->nullable();
            $table->decimal('quantity', 20, 6);
            $table->string('unit', 20);
            $table->decimal('purchase_price', 18, 6)->nullable();
            $table->char('purchase_currency', 3)->nullable();
            $table->date('purchase_date')->nullable();
            $table->decimal('manual_value', 18, 6)->nullable();
            $table->timestampTz('manual_value_updated_at')->nullable();
            $table->char('currency', 3)->default('EUR');
            $table->uuid('provider_id')->nullable();
            // Encrypted at rest via cast on model.
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('asset_type_id')->references('id')->on('asset_types')->restrictOnDelete();
            $table->foreign('provider_id')->references('id')->on('price_providers')->nullOnDelete();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'asset_type_id']);
            $table->index(['user_id', 'isin', 'status']);
            $table->index(['user_id', 'symbol', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investments');
    }
};
