<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The "Daily" board is the one that was always there and is not a row in
     * task_boards: its tasks are exactly the ones with a null task_board_id,
     * so every existing task (and every existing query filtering by
     * task_date) keeps working untouched. Tasks on a user-created board are
     * the opposite: they belong to a board and have no day at all, hence
     * task_date becoming nullable.
     */
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->date('task_date')->nullable()->change();
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('task_board_id')->nullable()->after('user_id')->constrained()->cascadeOnDelete();

            $table->index(['user_id', 'task_board_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'task_board_id', 'status']);
            $table->dropConstrainedForeignId('task_board_id');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->date('task_date')->nullable(false)->change();
        });
    }
};
