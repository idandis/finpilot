<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Uno spostamento di denaro tra due conti: non è né un'entrata né
     * un'uscita del budget, i soldi restano tuoi e cambiano solo tasca.
     *
     * I due lati sono facoltativi perché un lato può essere i contanti - un
     * prelievo al bancomat esce da un conto e non arriva su nessun altro - e
     * perché cancellare un conto non deve portarsi via lo storico.
     */
    public function up(): void
    {
        Schema::create('account_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_financial_account_id')->nullable()
                ->constrained('financial_accounts')->nullOnDelete();
            $table->foreignId('to_financial_account_id')->nullable()
                ->constrained('financial_accounts')->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('description')->nullable();
            $table->dateTime('transferred_at');
            $table->timestamps();

            $table->index(['user_id', 'transferred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_transfers');
    }
};
