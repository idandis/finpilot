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
        Schema::table('balance_sheet_month_closures', function (Blueprint $table) {
            // Cosa ha toccato la chiusura: senza questa traccia annullarla
            // significherebbe indovinare debiti e voci disattivate.
            $table->json('effects')->nullable()->after('cash_balance_after');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('balance_sheet_month_closures', function (Blueprint $table) {
            $table->dropColumn('effects');
        });
    }
};
