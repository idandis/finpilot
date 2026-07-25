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
        Schema::create('company_analysis_price_history', function (Blueprint $table) {
            $table->id();
            $table->string('symbol', 20);
            $table->date('price_date');
            $table->decimal('close_price', 20, 6);
            $table->decimal('open_price', 20, 6)->nullable();
            $table->decimal('high_price', 20, 6)->nullable();
            $table->decimal('low_price', 20, 6)->nullable();
            $table->timestamps();

            $table->unique(['symbol', 'price_date']);
        });

        Schema::table('company_analyses', function (Blueprint $table) {
            $table->timestamp('price_history_fetched_at')->nullable()->after('indicators_fetched_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_analyses', function (Blueprint $table) {
            $table->dropColumn('price_history_fetched_at');
        });

        Schema::dropIfExists('company_analysis_price_history');
    }
};
