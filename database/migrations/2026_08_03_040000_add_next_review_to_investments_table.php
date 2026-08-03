<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('investments', function (Blueprint $table) {
            $table->date('next_review_date')->nullable()->after('current_confidence');
            $table->text('next_review_note')->nullable()->after('next_review_date');
        });
    }

    public function down(): void
    {
        Schema::table('investments', function (Blueprint $table) {
            $table->dropColumn(['next_review_date', 'next_review_note']);
        });
    }
};
