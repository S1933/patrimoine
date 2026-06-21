<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fx_rates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->char('from_currency', 3);
            $table->char('to_currency', 3);
            $table->decimal('rate', 18, 10);
            $table->string('source', 60);
            $table->dateTimeTz('fetched_at');
            $table->timestampsTz();

            $table->index(['from_currency', 'to_currency', 'fetched_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fx_rates');
    }
};
