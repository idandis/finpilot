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
        Schema::table('company_analyses', function (Blueprint $table) {
            // Fase 3 - Valutazione: FCF Yield/Fair Value are numbers, the two
            // comparisons are inherently subjective (no objective feed can
            // supply them), so they're plain text notes like buffett_answers.
            $table->decimal('fcf_yield', 10, 4)->nullable()->after('peg_ratio');
            $table->decimal('fair_value', 20, 2)->nullable()->after('fcf_yield');
            $table->text('historical_comparison')->nullable()->after('fair_value');
            $table->text('competitor_comparison')->nullable()->after('historical_comparison');

            // Fase 1 - Qualità del business: same {key, rating, notes}[]
            // shape as buffett_answers, just a different fixed question list.
            $table->json('quality_assessments')->nullable()->after('buffett_answers');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_analyses', function (Blueprint $table) {
            $table->dropColumn([
                'fcf_yield', 'fair_value', 'historical_comparison', 'competitor_comparison', 'quality_assessments',
            ]);
        });
    }
};
