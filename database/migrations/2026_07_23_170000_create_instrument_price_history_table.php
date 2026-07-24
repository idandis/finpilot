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
        Schema::create('instrument_price_history', function (Blueprint $table) {
            $table->id();
            $table->string('isin', 12);
            $table->date('price_date');
            $table->decimal('close_price', 20, 6);
            $table->timestamps();

            $table->unique(['isin', 'price_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('instrument_price_history');
    }
};
