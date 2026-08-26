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
        Schema::create('balance_sheet_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('name');
            $table->string('category')->nullable();
            $table->decimal('amount', 14, 2)->nullable();
            $table->decimal('cash_used', 14, 2)->nullable();
            $table->decimal('hours_per_month', 8, 2)->nullable();
            $table->string('time_kind')->nullable();
            $table->string('frequency')->nullable()->default('monthly');
            $table->unsignedTinyInteger('progress_percent')->nullable();
            $table->boolean('active')->default(true);
            $table->foreignId('linked_asset_id')->nullable()->constrained('balance_sheet_entries')->nullOnDelete();
            $table->foreignId('linked_liability_id')->nullable()->constrained('balance_sheet_entries')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('balance_sheet_entries');
    }
};
