<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('opencode_api_key')->nullable()->after('remember_token');
            $table->string('opencode_model', 80)->nullable()->after('opencode_api_key');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['opencode_api_key', 'opencode_model']);
        });
    }
};
