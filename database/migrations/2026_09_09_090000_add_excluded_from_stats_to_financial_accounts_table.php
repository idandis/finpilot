<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Un conto che il budget non guarda proprio.
     *
     * Non va confuso con `hidden_from_stats`, che è l'archiviazione: quello
     * toglie il conto dai totali e dalle scelte, ma i suoi movimenti restano
     * in elenco. Questo va oltre - il conto resta usabile, il suo saldo si
     * calcola ancora, ma i movimenti che ci passano non compaiono nel mese e
     * non contano nello speso: serve alla carta che si tiene fuori dal
     * bilancio di casa.
     */
    public function up(): void
    {
        Schema::table('financial_accounts', function (Blueprint $table) {
            $table->boolean('excluded_from_stats')->default(false)->after('hidden_from_stats');
        });
    }

    public function down(): void
    {
        Schema::table('financial_accounts', function (Blueprint $table) {
            $table->dropColumn('excluded_from_stats');
        });
    }
};
