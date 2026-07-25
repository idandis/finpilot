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
        Schema::create('password_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('password_group_id')->constrained()->cascadeOnDelete();
            $table->string('platform_name');
            $table->string('username')->nullable();
            // Encrypted at rest via the model's 'encrypted' cast (Laravel's
            // AES-256-CBC using APP_KEY) - stored ciphertext is far larger
            // than any real password, hence text rather than string.
            $table->text('password');
            $table->timestamps();

            $table->index('password_group_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('password_entries');
    }
};
