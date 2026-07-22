<?php

namespace Database\Seeders;

use App\Models\TransactionCategory;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TransactionCategorySeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * The default system categories, available to every user.
     *
     * Colors are a validated categorical palette (12 slots, dark chart
     * surface #1a1a19), checked with the dataviz skill's validator for
     * lightness band, chroma floor, CVD separation and contrast - fixed
     * per category (not per-month) so a category's color never shifts
     * depending on which other categories appear in a given chart.
     *
     * @var array<string, string>
     */
    public const CATEGORIES = [
        'Casa' => '#3987e5',
        'Alimentari' => '#008300',
        'Bollette' => '#d55181',
        'Trasporti' => '#c98500',
        'Salute' => '#199e70',
        'Shopping' => '#d95926',
        'Ristoranti' => '#9085e9',
        'Viaggi' => '#e66767',
        'Abbonamenti' => '#c026d3',
        'Formazione' => '#65a30d',
        'Investimenti' => '#0891b2',
        'Risparmio' => '#4d7c0f',
    ];

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        foreach (self::CATEGORIES as $name => $color) {
            TransactionCategory::firstOrCreate(
                ['user_id' => null, 'name' => $name],
                ['color' => $color, 'is_system' => true],
            );
        }
    }
}
