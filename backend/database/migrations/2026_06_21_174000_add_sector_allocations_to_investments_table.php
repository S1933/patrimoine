<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('investments', function (Blueprint $table) {
            if (! Schema::hasColumn('investments', 'sector_allocations')) {
                $table->jsonb('sector_allocations')->nullable()->after('country_allocations');
            }
        });
    }

    public function down(): void
    {
        Schema::table('investments', function (Blueprint $table) {
            if (Schema::hasColumn('investments', 'sector_allocations')) {
                $table->dropColumn('sector_allocations');
            }
        });
    }
};
