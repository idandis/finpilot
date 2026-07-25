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
        Schema::create('company_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('symbol');

            // The 10 indicators the user tracks before buying a company.
            // Percentages/ratios stored as plain decimals (e.g. 0.157 =
            // 15.7%); free_cash_flow is an absolute amount in
            // indicators_currency. All nullable: always manually editable
            // even when the EODHD fundamentals fetch is unavailable/fails.
            $table->decimal('revenue_growth', 10, 6)->nullable();
            $table->decimal('eps_growth', 10, 6)->nullable();
            $table->decimal('free_cash_flow', 20, 2)->nullable();
            $table->decimal('operating_margin', 10, 6)->nullable();
            $table->decimal('roe', 10, 6)->nullable();
            $table->decimal('roic', 10, 6)->nullable();
            $table->decimal('debt_to_ebitda', 10, 4)->nullable();
            $table->decimal('pe_ratio', 10, 4)->nullable();
            $table->decimal('ev_to_ebitda', 10, 4)->nullable();
            $table->decimal('peg_ratio', 10, 4)->nullable();
            $table->string('indicators_currency', 3)->nullable();
            $table->timestamp('indicators_fetched_at')->nullable();

            $table->json('buffett_answers')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'symbol']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_analyses');
    }
};
