<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investment_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('investment_id')->constrained()->cascadeOnDelete();
            $table->date('review_date');
            $table->string('decision');
            $table->unsignedTinyInteger('score_before')->nullable();
            $table->unsignedTinyInteger('score_after')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['investment_id', 'review_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investment_reviews');
    }
};
