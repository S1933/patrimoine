<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('investments', function (Blueprint $table) {
            if (! Schema::hasColumn('investments', 'geography')) {
                $table->string('geography', 50)->nullable()->after('unit');
                $table->index(['user_id', 'geography']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('investments', function (Blueprint $table) {
            if (Schema::hasColumn('investments', 'geography')) {
                $table->dropIndex('investments_user_id_geography_index');
                $table->dropColumn('geography');
            }
        });
    }
};
