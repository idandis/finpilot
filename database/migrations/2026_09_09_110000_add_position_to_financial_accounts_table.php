<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * L'ordine dei conti, deciso a mano.
     *
     * Fino a qui l'elenco era alfabetico dappertutto - pagina dei conti, tile
     * del mese, selettore dei movimenti - e la carta che si usa ogni giorno
     * poteva finire in fondo. Con una posizione l'ordine lo sceglie chi tiene
     * il budget, trascinando le righe.
     *
     * Le righe già esistenti partono dall'ordine alfabetico che avevano: così
     * il primo caricamento dopo la migrazione non sposta niente sotto gli
     * occhi di nessuno.
     */
    public function up(): void
    {
        Schema::table('financial_accounts', function (Blueprint $table) {
            $table->unsignedInteger('position')->default(0)->after('icon');
        });

        DB::table('financial_accounts')
            ->select('id', 'user_id')
            ->orderBy('user_id')
            ->orderBy('name')
            ->get()
            ->groupBy('user_id')
            ->each(function ($accounts) {
                foreach ($accounts->values() as $position => $account) {
                    DB::table('financial_accounts')
                        ->where('id', $account->id)
                        ->update(['position' => $position]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('financial_accounts', function (Blueprint $table) {
            $table->dropColumn('position');
        });
    }
};
