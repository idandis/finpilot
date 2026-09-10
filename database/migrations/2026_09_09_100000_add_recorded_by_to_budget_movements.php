<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Chi ha registrato il movimento.
     *
     * In un budget condiviso i movimenti stanno tutti sotto al proprietario,
     * ma li scrive chi capita: senza questa colonna, riaprendo il mese non si
     * sa più chi ha segnato cosa.
     *
     * Resta nulla sui movimenti già esistenti: chi li ha scritti non è mai
     * stato registrato, e attribuirli al proprietario sarebbe un'invenzione.
     */
    public function up(): void
    {
        foreach (['budget_expenses', 'account_transfers'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->foreignId('recorded_by_user_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('users')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['budget_expenses', 'account_transfers'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropConstrainedForeignId('recorded_by_user_id');
            });
        }
    }
};
