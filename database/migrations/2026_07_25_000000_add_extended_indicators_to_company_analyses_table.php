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
            $table->decimal('net_margin', 10, 6)->nullable()->after('operating_margin');
            $table->decimal('gross_margin', 10, 6)->nullable()->after('net_margin');
            $table->decimal('revenue_cagr_5y', 10, 6)->nullable()->after('eps_growth');
            $table->decimal('eps_cagr_5y', 10, 6)->nullable()->after('revenue_cagr_5y');
            $table->decimal('interest_coverage', 12, 4)->nullable()->after('debt_to_ebitda');
            $table->decimal('current_ratio', 12, 4)->nullable()->after('interest_coverage');
            $table->decimal('fcf_margin', 10, 6)->nullable()->after('free_cash_flow');
            $table->decimal('ev_to_fcf', 12, 4)->nullable()->after('ev_to_ebitda');
            $table->decimal('price_to_sales', 12, 4)->nullable()->after('ev_to_fcf');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_analyses', function (Blueprint $table) {
            $table->dropColumn([
                'net_margin', 'gross_margin', 'revenue_cagr_5y', 'eps_cagr_5y',
                'interest_coverage', 'current_ratio', 'fcf_margin', 'ev_to_fcf', 'price_to_sales',
            ]);
        });
    }
};
