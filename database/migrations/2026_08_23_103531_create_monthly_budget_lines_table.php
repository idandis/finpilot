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
        Schema::create('monthly_budget_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monthly_budget_id')->constrained()->cascadeOnDelete();
            $table->foreignId('budget_subcategory_id')->constrained()->cascadeOnDelete();
            $table->decimal('planned_amount', 10, 2)->default(0);
            $table->timestamps();
            $table->unique(['monthly_budget_id', 'budget_subcategory_id'], 'monthly_budget_lines_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('monthly_budget_lines');
    }
};
