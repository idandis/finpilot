<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Who a meal plan is shared with. Unlike a task board or a shopping
     * list there is no plan row anywhere: a plan simply *is* a user's meals,
     * so the "container" here is the owner themselves and this table pairs
     * them with the people they invited (see User::mealPlanIsAccessibleBy()).
     */
    public function up(): void
    {
        Schema::create('meal_plan_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('member_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['owner_user_id', 'member_user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meal_plan_members');
    }
};
