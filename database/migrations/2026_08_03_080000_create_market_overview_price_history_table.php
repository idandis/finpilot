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
        Schema::create('market_overview_price_history', function (Blueprint $table) {
            $table->id();
            $table->string('instrument_key', 32);
            $table->date('price_date');
            $table->decimal('close_price', 20, 6);
            $table->decimal('open_price', 20, 6)->nullable();
            $table->decimal('high_price', 20, 6)->nullable();
            $table->decimal('low_price', 20, 6)->nullable();
            $table->timestamps();

            $table->unique(['instrument_key', 'price_date'], 'market_overview_price_history_key_date_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('market_overview_price_history');
    }
};
