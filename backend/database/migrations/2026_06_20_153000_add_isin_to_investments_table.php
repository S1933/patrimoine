<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('investments', function (Blueprint $table) {
            if (! Schema::hasColumn('investments', 'isin')) {
                $table->string('isin', 12)->nullable()->after('name');
                $table->index(['user_id', 'isin', 'status']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('investments', function (Blueprint $table) {
            if (Schema::hasColumn('investments', 'isin')) {
                $table->dropIndex('investments_user_id_isin_status_index');
                $table->dropColumn('isin');
            }
        });
    }
};
