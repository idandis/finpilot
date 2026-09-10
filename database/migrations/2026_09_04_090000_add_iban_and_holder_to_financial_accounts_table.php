<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Un conto va riconosciuto anche quando la banca è la stessa: l'IBAN e
     * l'intestatario sono quello che distingue il conto mio da quello di casa.
     */
    public function up(): void
    {
        Schema::table('financial_accounts', function (Blueprint $table) {
            $table->string('iban', 34)->nullable()->after('bank_name');
            $table->string('holder_name')->nullable()->after('iban');
        });
    }

    public function down(): void
    {
        Schema::table('financial_accounts', function (Blueprint $table) {
            $table->dropColumn(['iban', 'holder_name']);
        });
    }
};
