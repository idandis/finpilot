<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investment_journal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('investment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->string('entry_type')->default('note');
            $table->text('note');
            $table->dateTime('occurred_at');
            $table->timestamps();

            $table->index(['investment_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investment_journal_entries');
    }
};
