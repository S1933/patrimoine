<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portfolio_snapshots', function (Blueprint $table) {
            $table->decimal('fx_rate', 18, 10)->nullable()->after('currency');
            $table->string('fx_source', 60)->nullable()->after('fx_rate');
            $table->char('fx_from_currency', 3)->nullable()->after('fx_source');
        });
    }

    public function down(): void
    {
        Schema::table('portfolio_snapshots', function (Blueprint $table) {
            $table->dropColumn(['fx_rate', 'fx_source', 'fx_from_currency']);
        });
    }
};
