<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('investment_id')->constrained()->cascadeOnDelete();
            $table->string('event_type');
            $table->string('title');
            $table->date('event_date');
            $table->json('metrics')->nullable();
            $table->text('summary')->nullable();
            $table->timestamps();

            $table->index(['investment_id', 'event_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investment_events');
    }
};
