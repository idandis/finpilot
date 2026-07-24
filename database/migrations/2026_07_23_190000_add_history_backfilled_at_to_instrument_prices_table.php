<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('instrument_prices', function (Blueprint $table) {
            $table->timestamp('history_backfilled_at')->nullable()->after('fetched_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('instrument_prices', function (Blueprint $table) {
            $table->dropColumn('history_backfilled_at');
        });
    }
};
