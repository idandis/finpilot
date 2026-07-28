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
            $table->decimal('realtime_price', 20, 6)->nullable()->after('last_price');
            $table->timestamp('realtime_fetched_at')->nullable()->after('fetched_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('instrument_prices', function (Blueprint $table) {
            $table->dropColumn(['realtime_price', 'realtime_fetched_at']);
        });
    }
};
