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
        Schema::table('meals', function (Blueprint $table) {
            // Nullable: only set for meals created by dragging a preconfigured
            // dish onto the week - ad-hoc meals have no dish behind them. Set
            // null (not cascade-deleted) if the source dish is later removed,
            // so the meal itself stays intact, just loses its ingredient link.
            $table->foreignId('dish_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('meals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('dish_id');
        });
    }
};
