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
        Schema::table('instrument_price_history', function (Blueprint $table) {
            $table->decimal('open_price', 20, 6)->nullable()->after('close_price');
            $table->decimal('high_price', 20, 6)->nullable()->after('open_price');
            $table->decimal('low_price', 20, 6)->nullable()->after('high_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('instrument_price_history', function (Blueprint $table) {
            $table->dropColumn(['open_price', 'high_price', 'low_price']);
        });
    }
};
