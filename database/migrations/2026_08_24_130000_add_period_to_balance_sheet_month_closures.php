<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('balance_sheet_month_closures', function (Blueprint $table) {
            $table->year('year')->nullable()->after('user_id');
            $table->unsignedTinyInteger('month')->nullable()->after('year');
            $table->index(['user_id', 'year', 'month']);
        });

        // Le chiusure già registrate prendono il periodo dalla data di chiusura.
        // In PHP e non in SQL: YEAR()/MONTH() non esistono su SQLite.
        DB::table('balance_sheet_month_closures')
            ->whereNull('year')
            ->get(['id', 'created_at'])
            ->each(function (object $closure) {
                $closedAt = $closure->created_at ? Carbon::parse($closure->created_at) : Carbon::now();

                DB::table('balance_sheet_month_closures')
                    ->where('id', $closure->id)
                    ->update(['year' => $closedAt->year, 'month' => $closedAt->month]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('balance_sheet_month_closures', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'year', 'month']);
            $table->dropColumn(['year', 'month']);
        });
    }
};
