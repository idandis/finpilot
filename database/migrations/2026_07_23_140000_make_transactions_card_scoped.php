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
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropUnique(['financial_account_id', 'dedup_hash']);
            $table->dropIndex(['financial_account_id', 'transaction_date']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('financial_account_id')->nullable()->change();
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->unique(['card_id', 'dedup_hash']);
            $table->index(['card_id', 'transaction_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropUnique(['card_id', 'dedup_hash']);
            $table->dropIndex(['card_id', 'transaction_date']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('financial_account_id')->nullable(false)->change();
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->unique(['financial_account_id', 'dedup_hash']);
            $table->index(['financial_account_id', 'transaction_date']);
        });
    }
};
