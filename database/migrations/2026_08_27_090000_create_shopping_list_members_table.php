<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Who a list is shared with, exactly like task_board_members: the owner
     * (shopping_lists.user_id) is never a row here, and the two together
     * are the list's people (see ShoppingList::isAccessibleBy()).
     */
    public function up(): void
    {
        Schema::create('shopping_list_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shopping_list_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['shopping_list_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shopping_list_members');
    }
};
