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
        Schema::create('instrument_news', function (Blueprint $table) {
            $table->id();
            $table->string('isin', 12);
            $table->timestamp('published_at');
            $table->string('title');
            $table->text('content')->nullable();
            $table->string('url')->nullable();
            $table->decimal('sentiment_polarity', 6, 4)->nullable();
            $table->json('tags')->nullable();
            $table->timestamps();

            $table->unique(['isin', 'url']);
            $table->index(['isin', 'published_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('instrument_news');
    }
};
