<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'opencode_provider')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('opencode_provider', 20)->nullable()->after('opencode_model');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'opencode_provider')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('opencode_provider');
            });
        }
    }
};
