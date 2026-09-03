<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Who a budget is shared with. Like a meal plan, and unlike a task board
     * or a shopping list, there is no budget row anywhere: a budget simply
     * *is* a user's categories and months, so the "container" here is the
     * owner themselves and this table pairs them with the people they
     * invited (see User::budgetIsAccessibleBy()).
     */
    public function up(): void
    {
        Schema::create('budget_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('member_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['owner_user_id', 'member_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_members');
    }
};
