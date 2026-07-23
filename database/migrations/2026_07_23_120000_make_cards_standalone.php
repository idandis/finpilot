<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->foreignId('financial_account_id')->nullable()->change();
            $table->string('color', 7)->nullable()->after('type');
            $table->string('icon')->nullable()->after('color');
            $table->string('owner_name')->nullable()->after('circuit');
            $table->string('iban', 34)->nullable()->after('owner_name');
        });

        // Backfill: le carte esistenti derivano il proprietario dal conto già collegato.
        DB::statement(<<<'SQL'
            UPDATE cards
            SET user_id = (
                SELECT user_id FROM financial_accounts WHERE financial_accounts.id = cards.financial_account_id
            )
            WHERE cards.financial_account_id IS NOT NULL
        SQL);

        Schema::table('cards', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn(['color', 'icon', 'owner_name', 'iban']);
            $table->foreignId('financial_account_id')->nullable(false)->change();
        });
    }
};
