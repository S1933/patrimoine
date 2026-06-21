<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('investments', function (Blueprint $table) {
            if (! Schema::hasColumn('investments', 'country_allocations')) {
                $table->jsonb('country_allocations')->nullable()->after('geography');
            }
        });
    }

    public function down(): void
    {
        Schema::table('investments', function (Blueprint $table) {
            if (Schema::hasColumn('investments', 'country_allocations')) {
                $table->dropColumn('country_allocations');
            }
        });
    }
};
