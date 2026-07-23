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
        Schema::table('category_budgets', function (Blueprint $table) {
            $table->foreignId('card_id')->nullable()->after('transaction_category_id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('category_budgets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('card_id');
        });
    }
};
