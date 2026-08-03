<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('investment_reviews', function (Blueprint $table) {
            $table->foreignId('investment_event_id')->nullable()->after('investment_id')->constrained()->nullOnDelete();
            $table->boolean('thesis_still_valid')->nullable()->after('decision');
        });
    }

    public function down(): void
    {
        Schema::table('investment_reviews', function (Blueprint $table) {
            $table->dropConstrainedForeignId('investment_event_id');
            $table->dropColumn('thesis_still_valid');
        });
    }
};
