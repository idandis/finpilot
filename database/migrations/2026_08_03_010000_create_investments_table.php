<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('isin', 12);
            $table->foreignId('company_analysis_id')->nullable()->constrained()->nullOnDelete();
            $table->json('motivation_reasons')->nullable();
            $table->text('motivation_note')->nullable();
            $table->text('thesis')->nullable();
            $table->text('sell_conditions')->nullable();
            $table->string('time_horizon')->nullable();
            $table->unsignedTinyInteger('initial_confidence')->nullable();
            $table->unsignedTinyInteger('current_confidence')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'isin']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investments');
    }
};
