<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Il budget che si apre per primo.
     *
     * Chi lavora soprattutto sul budget di casa - quello che gli ha condiviso
     * un'altra persona - non deve sceglierlo ogni volta dal menù: qui si dice
     * quale aprire all'avvio. Nullo vuol dire il proprio, com'è sempre stato.
     *
     * Punta al proprietario del budget, non a una riga "budget": un budget è
     * l'insieme di categorie e mesi di un utente, non ha una tabella sua.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('default_budget_user_id')
                ->nullable()
                ->after('id')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('default_budget_user_id');
        });
    }
};
