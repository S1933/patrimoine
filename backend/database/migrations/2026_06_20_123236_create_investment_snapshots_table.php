<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investment_snapshots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('investment_id');
            $table->uuid('user_id');
            $table->date('snapshot_date');
            $table->decimal('quantity', 20, 6);
            $table->decimal('price', 18, 6);
            $table->decimal('value', 20, 6);
            $table->decimal('cost', 20, 6)->default(0);
            $table->char('currency', 3)->default('EUR');
            $table->timestampsTz();

            $table->foreign('investment_id')->references('id')->on('investments')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();

            $table->index(['investment_id', 'snapshot_date']);
            $table->index(['user_id', 'snapshot_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investment_snapshots');
    }
};
