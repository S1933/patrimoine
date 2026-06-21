<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investment_strategy_allocations', function (Blueprint $table) {
            $table->id();
            $table->uuid('user_id');
            $table->unsignedSmallInteger('asset_type_id');
            $table->decimal('target_percent', 5, 2);
            $table->timestampsTz();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('asset_type_id')->references('id')->on('asset_types')->restrictOnDelete();
            $table->unique(['user_id', 'asset_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investment_strategy_allocations');
    }
};
