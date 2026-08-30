<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Who a board is shared with. The owner (task_boards.user_id) is never a
     * row here: membership is exactly "the people the owner invited", and
     * both together are the board's people (see TaskBoard::isAccessibleBy()).
     */
    public function up(): void
    {
        Schema::create('task_board_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_board_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['task_board_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_board_members');
    }
};
