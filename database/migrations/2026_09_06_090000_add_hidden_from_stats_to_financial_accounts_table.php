<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Un conto fuori dai conteggi.
     *
     * Non è "disattivato" - i movimenti continuano a passarci - ma resta
     * fuori dal riepilogo del budget: serve per i conti di qualcun altro,
     * per un salvadanaio o per una carta che non si vuole vedere ogni volta.
     */
    public function up(): void
    {
        Schema::table('financial_accounts', function (Blueprint $table) {
            $table->boolean('hidden_from_stats')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('financial_accounts', function (Blueprint $table) {
            $table->dropColumn('hidden_from_stats');
        });
    }
};
