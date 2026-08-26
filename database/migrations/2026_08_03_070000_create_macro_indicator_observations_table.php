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
        Schema::create('macro_indicator_observations', function (Blueprint $table) {
            $table->id();
            $table->string('indicator_key', 64);
            $table->date('observation_date');
            $table->decimal('value', 20, 6);
            $table->date('published_at')->nullable();
            $table->timestamps();

            $table->unique(['indicator_key', 'observation_date'], 'macro_indicator_observations_key_date_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('macro_indicator_observations');
    }
};
