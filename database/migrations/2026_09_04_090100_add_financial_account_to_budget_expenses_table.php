<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Da dove sono usciti (o entrati) i soldi. Resta facoltativo: nullo vuol
     * dire contanti, e i movimenti registrati prima di avere i conti non
     * vanno persi né riassegnati d'ufficio a un conto qualunque.
     */
    public function up(): void
    {
        Schema::table('budget_expenses', function (Blueprint $table) {
            $table->foreignId('financial_account_id')
                ->nullable()
                ->after('budget_subcategory_id')
                ->constrained()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('budget_expenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('financial_account_id');
        });
    }
};
