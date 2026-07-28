<?php

namespace Tests\Feature\AiChat;

use App\Models\Dish;
use App\Models\Meal;
use App\Models\User;
use App\Services\Ai\AiToolExecutor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiToolExecutorTest extends TestCase
{
    use RefreshDatabase;

    public function test_pianifica_pasti_creates_meals_for_the_given_user()
    {
        $user = User::factory()->create();
        $executor = app(AiToolExecutor::class);

        $result = $executor->execute('pianifica_pasti', [
            'pasti' => [
                ['data' => '2026-08-03', 'tipo' => 'lunch', 'titolo' => 'Pasta al pomodoro'],
                ['data' => '2026-08-03', 'tipo' => 'dinner', 'titolo' => 'Pollo alla griglia', 'categoria' => 'carne'],
            ],
        ], $user);

        $this->assertSame(2, $result['inseriti']);
        $this->assertCount(0, $result['errori']);
        $this->assertDatabaseHas('meals', [
            'user_id' => $user->id,
            'title' => 'Pasta al pomodoro',
            'meal_date' => '2026-08-03 00:00:00',
            'meal_type' => 'lunch',
        ]);
        $this->assertDatabaseHas('meals', [
            'user_id' => $user->id,
            'title' => 'Pollo alla griglia',
            'meal_date' => '2026-08-03 00:00:00',
            'meal_type' => 'dinner',
            'category' => 'carne',
        ]);
    }

    public function test_pianifica_pasti_appends_after_existing_meals_in_the_same_slot()
    {
        $user = User::factory()->create();
        Meal::factory()->create([
            'user_id' => $user->id,
            'meal_date' => '2026-08-03',
            'meal_type' => 'lunch',
            'position' => 0,
        ]);
        $executor = app(AiToolExecutor::class);

        $executor->execute('pianifica_pasti', [
            'pasti' => [
                ['data' => '2026-08-03', 'tipo' => 'lunch', 'titolo' => 'Nuovo piatto'],
            ],
        ], $user);

        $this->assertDatabaseHas('meals', [
            'title' => 'Nuovo piatto',
            'position' => 1,
        ]);
    }

    public function test_pianifica_pasti_reports_invalid_entries_without_failing_the_whole_batch()
    {
        $user = User::factory()->create();
        $executor = app(AiToolExecutor::class);

        $result = $executor->execute('pianifica_pasti', [
            'pasti' => [
                ['data' => 'non-una-data', 'tipo' => 'lunch', 'titolo' => 'Valido? No'],
                ['data' => '2026-08-04', 'tipo' => 'breakfast', 'titolo' => 'Tipo non valido'],
                ['data' => '2026-08-04', 'tipo' => 'lunch', 'titolo' => 'Questo sì'],
            ],
        ], $user);

        $this->assertSame(1, $result['inseriti']);
        $this->assertCount(2, $result['errori']);
        $this->assertDatabaseHas('meals', ['title' => 'Questo sì']);
        $this->assertDatabaseMissing('meals', ['title' => 'Valido? No']);
    }

    public function test_pianifica_pasti_ignores_an_unknown_category_instead_of_failing()
    {
        $user = User::factory()->create();
        $executor = app(AiToolExecutor::class);

        $result = $executor->execute('pianifica_pasti', [
            'pasti' => [
                ['data' => '2026-08-05', 'tipo' => 'dinner', 'titolo' => 'Piatto misterioso', 'categoria' => 'non_esiste'],
            ],
        ], $user);

        $this->assertSame(1, $result['inseriti']);
        $this->assertDatabaseHas('meals', ['title' => 'Piatto misterioso', 'category' => null]);
    }

    public function test_pianifica_pasti_skips_an_exact_duplicate_already_on_file()
    {
        $user = User::factory()->create();
        Meal::factory()->create([
            'user_id' => $user->id,
            'title' => 'Pasta al pomodoro',
            'meal_date' => '2026-08-03',
            'meal_type' => 'lunch',
            'position' => 0,
        ]);
        $executor = app(AiToolExecutor::class);

        $result = $executor->execute('pianifica_pasti', [
            'pasti' => [
                ['data' => '2026-08-03', 'tipo' => 'lunch', 'titolo' => 'Pasta al pomodoro'],
            ],
        ], $user);

        $this->assertSame(0, $result['inseriti']);
        $this->assertCount(1, $result['errori']);
        $this->assertDatabaseCount('meals', 1);
    }

    public function test_pianifica_pasti_skips_a_duplicate_within_the_same_batch()
    {
        $user = User::factory()->create();
        $executor = app(AiToolExecutor::class);

        $result = $executor->execute('pianifica_pasti', [
            'pasti' => [
                ['data' => '2026-08-03', 'tipo' => 'lunch', 'titolo' => 'Pasta al pomodoro'],
                ['data' => '2026-08-03', 'tipo' => 'lunch', 'titolo' => 'Pasta al pomodoro'],
            ],
        ], $user);

        $this->assertSame(1, $result['inseriti']);
        $this->assertCount(1, $result['errori']);
        $this->assertDatabaseCount('meals', 1);
    }

    public function test_pianifica_pasti_requires_at_least_one_meal()
    {
        $user = User::factory()->create();
        $executor = app(AiToolExecutor::class);

        $result = $executor->execute('pianifica_pasti', ['pasti' => []], $user);

        $this->assertArrayHasKey('errore', $result);
        $this->assertDatabaseCount('meals', 0);
    }

    public function test_piatti_preconfigurati_lists_the_users_dish_library_with_ingredients()
    {
        $user = User::factory()->create();
        $dish = Dish::factory()->create(['user_id' => $user->id, 'name' => 'Riso basmati con pollo al curry', 'category' => 'carne']);
        $dish->ingredients()->create(['name' => 'Riso basmati', 'category' => 'pasta_riso_cereali']);
        $dish->ingredients()->create(['name' => 'Pollo', 'category' => 'carne']);
        Dish::factory()->create(['user_id' => User::factory(), 'name' => 'Piatto di un altro utente']);

        $executor = app(AiToolExecutor::class);
        $result = $executor->execute('piatti_preconfigurati', [], $user);

        $this->assertCount(1, $result['piatti']);
        $this->assertSame('Riso basmati con pollo al curry', $result['piatti'][0]['nome']);
        $this->assertSame(['Riso basmati', 'Pollo'], $result['piatti'][0]['ingredienti']);
    }

    public function test_crea_piatti_preconfigurati_creates_dishes_with_ingredients()
    {
        $user = User::factory()->create();
        $executor = app(AiToolExecutor::class);

        $result = $executor->execute('crea_piatti_preconfigurati', [
            'piatti' => [
                [
                    'nome' => 'Couscous con verdure',
                    'categoria' => 'verdure',
                    'ingredienti' => [
                        ['nome' => 'Couscous', 'categoria' => 'pasta_riso_cereali'],
                        ['nome' => 'Zucchine'],
                    ],
                ],
            ],
        ], $user);

        $this->assertSame(1, $result['inseriti']);
        $this->assertDatabaseHas('dishes', ['user_id' => $user->id, 'name' => 'Couscous con verdure', 'category' => 'verdure']);
        $this->assertDatabaseHas('dish_ingredients', ['name' => 'Couscous', 'category' => 'pasta_riso_cereali']);
        $this->assertDatabaseHas('dish_ingredients', ['name' => 'Zucchine', 'category' => 'altro']);
    }

    public function test_crea_piatti_preconfigurati_requires_a_valid_category()
    {
        $user = User::factory()->create();
        $executor = app(AiToolExecutor::class);

        $result = $executor->execute('crea_piatti_preconfigurati', [
            'piatti' => [['nome' => 'Senza categoria valida', 'categoria' => 'non_esiste']],
        ], $user);

        $this->assertSame(0, $result['inseriti']);
        $this->assertCount(1, $result['errori']);
        $this->assertDatabaseMissing('dishes', ['name' => 'Senza categoria valida']);
    }

    public function test_crea_piatti_preconfigurati_skips_a_name_already_in_the_library()
    {
        $user = User::factory()->create();
        Dish::factory()->create(['user_id' => $user->id, 'name' => 'Couscous con verdure']);
        $executor = app(AiToolExecutor::class);

        $result = $executor->execute('crea_piatti_preconfigurati', [
            'piatti' => [['nome' => 'Couscous con verdure', 'categoria' => 'verdure']],
        ], $user);

        $this->assertSame(0, $result['inseriti']);
        $this->assertCount(1, $result['errori']);
        $this->assertDatabaseCount('dishes', 1);
    }
}
