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
        Schema::create('instrument_prices', function (Blueprint $table) {
            $table->id();
            $table->string('isin', 12)->unique();
            $table->string('code')->nullable();
            $table->string('exchange')->nullable();
            $table->boolean('resolution_failed')->default(false);
            $table->decimal('last_price', 20, 6)->nullable();
            $table->string('currency', 3)->nullable();
            $table->date('price_date')->nullable();
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('instrument_prices');
    }
};
