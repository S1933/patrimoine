<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_types', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('code', 20)->unique();
            $table->string('label', 50);
            $table->string('default_provider', 50)->nullable();
            $table->string('default_unit', 20)->nullable();
            $table->boolean('is_priced_externally')->default(false);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_types');
    }
};
