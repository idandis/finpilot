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
        Schema::table('budget_categories', function (Blueprint $table) {
            // 'expense' oppure 'income': le sottocategorie e i movimenti
            // ereditano il verso dalla categoria che li contiene.
            $table->string('type')->default('expense')->after('monthly_budget_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('budget_categories', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
