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
        Schema::table('company_analyses', function (Blueprint $table) {
            $table->dropColumn('quality_assessments');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_analyses', function (Blueprint $table) {
            $table->json('quality_assessments')->nullable()->after('buffett_answers');
        });
    }
};
