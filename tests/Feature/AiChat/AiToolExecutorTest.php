<?php

namespace Tests\Feature\AiChat;

use App\Models\Dish;
use App\Models\Exercise;
use App\Models\Meal;
use App\Models\User;
use App\Models\Workout;
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

    public function test_crea_esercizi_creates_exercises_defaulting_to_bodyweight()
    {
        $user = User::factory()->create();
        $executor = app(AiToolExecutor::class);

        $result = $executor->execute('crea_esercizi', [
            'esercizi' => [
                ['nome' => 'Push-up', 'categoria' => 'braccia'],
                ['nome' => 'Trazioni alla sbarra', 'categoria' => 'schiena', 'corpo_libero' => false],
            ],
        ], $user);

        $this->assertSame(2, $result['inseriti']);
        $this->assertCount(0, $result['errori']);
        $this->assertDatabaseHas('exercises', ['user_id' => $user->id, 'name' => 'Push-up', 'category' => 'braccia', 'requires_equipment' => false]);
        $this->assertDatabaseHas('exercises', ['user_id' => $user->id, 'name' => 'Trazioni alla sbarra', 'requires_equipment' => true]);
    }

    public function test_crea_esercizi_requires_a_valid_category()
    {
        $user = User::factory()->create();
        $executor = app(AiToolExecutor::class);

        $result = $executor->execute('crea_esercizi', [
            'esercizi' => [['nome' => 'Senza categoria valida', 'categoria' => 'non_esiste']],
        ], $user);

        $this->assertSame(0, $result['inseriti']);
        $this->assertCount(1, $result['errori']);
        $this->assertDatabaseMissing('exercises', ['name' => 'Senza categoria valida']);
    }

    public function test_crea_esercizi_skips_a_name_already_in_the_library()
    {
        $user = User::factory()->create();
        Exercise::factory()->create(['user_id' => $user->id, 'name' => 'Push-up']);
        $executor = app(AiToolExecutor::class);

        $result = $executor->execute('crea_esercizi', [
            'esercizi' => [['nome' => 'Push-up', 'categoria' => 'braccia']],
        ], $user);

        $this->assertSame(0, $result['inseriti']);
        $this->assertCount(1, $result['errori']);
        $this->assertDatabaseCount('exercises', 1);
    }

    public function test_esercizi_disponibili_lists_the_users_exercise_library()
    {
        $user = User::factory()->create();
        Exercise::factory()->create(['user_id' => $user->id, 'name' => 'Push-up', 'category' => 'braccia', 'requires_equipment' => false]);
        Exercise::factory()->create(['user_id' => User::factory(), 'name' => 'Esercizio di un altro utente']);

        $executor = app(AiToolExecutor::class);
        $result = $executor->execute('esercizi_disponibili', [], $user);

        $this->assertCount(1, $result['esercizi']);
        $this->assertSame('Push-up', $result['esercizi'][0]['nome']);
        $this->assertTrue($result['esercizi'][0]['corpo_libero']);
    }

    public function test_pianifica_allenamenti_creates_a_workout_with_sets()
    {
        $user = User::factory()->create();
        Exercise::factory()->create(['user_id' => $user->id, 'name' => 'Push-up']);
        $executor = app(AiToolExecutor::class);

        $result = $executor->execute('pianifica_allenamenti', [
            'allenamenti' => [
                ['data' => '2026-08-03', 'esercizi' => [['nome' => 'Push-up', 'serie' => 3, 'ripetizioni' => 15]]],
            ],
        ], $user);

        $this->assertSame(1, $result['inseriti']);
        $this->assertCount(0, $result['errori']);
        $this->assertDatabaseHas('workouts', ['user_id' => $user->id, 'workout_date' => '2026-08-03 00:00:00']);
        $this->assertDatabaseHas('workout_exercises', ['sets_count' => 3, 'reps_count' => 15]);
        $this->assertDatabaseCount('workout_sets', 3);
    }

    public function test_pianifica_allenamenti_reuses_the_days_existing_workout()
    {
        $user = User::factory()->create();
        $exerciseOne = Exercise::factory()->create(['user_id' => $user->id, 'name' => 'Push-up']);
        $exerciseTwo = Exercise::factory()->create(['user_id' => $user->id, 'name' => 'Squat']);
        $workout = Workout::factory()->create(['user_id' => $user->id, 'workout_date' => '2026-08-03']);
        $workout->exercises()->create(['exercise_id' => $exerciseOne->id, 'sets_count' => 3, 'reps_count' => 15, 'position' => 0]);
        $executor = app(AiToolExecutor::class);

        $executor->execute('pianifica_allenamenti', [
            'allenamenti' => [
                ['data' => '2026-08-03', 'esercizi' => [['nome' => 'Squat', 'serie' => 4, 'ripetizioni' => 10]]],
            ],
        ], $user);

        $this->assertDatabaseCount('workouts', 1);
        $this->assertDatabaseHas('workout_exercises', ['exercise_id' => $exerciseTwo->id, 'position' => 1]);
    }

    public function test_pianifica_allenamenti_reports_a_missing_exercise_without_failing_the_batch()
    {
        $user = User::factory()->create();
        $executor = app(AiToolExecutor::class);

        $result = $executor->execute('pianifica_allenamenti', [
            'allenamenti' => [
                ['data' => '2026-08-03', 'esercizi' => [['nome' => 'Esercizio inesistente', 'serie' => 3, 'ripetizioni' => 10]]],
            ],
        ], $user);

        $this->assertSame(0, $result['inseriti']);
        $this->assertCount(1, $result['errori']);
        $this->assertStringContainsString('crea_esercizi', $result['errori'][0]);
        $this->assertDatabaseCount('workout_exercises', 0);
    }

    public function test_pianifica_allenamenti_skips_an_exercise_already_in_the_days_workout()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id, 'name' => 'Push-up']);
        $workout = Workout::factory()->create(['user_id' => $user->id, 'workout_date' => '2026-08-03']);
        $workout->exercises()->create(['exercise_id' => $exercise->id, 'sets_count' => 3, 'reps_count' => 15, 'position' => 0]);
        $executor = app(AiToolExecutor::class);

        $result = $executor->execute('pianifica_allenamenti', [
            'allenamenti' => [
                ['data' => '2026-08-03', 'esercizi' => [['nome' => 'Push-up', 'serie' => 3, 'ripetizioni' => 15]]],
            ],
        ], $user);

        $this->assertSame(0, $result['inseriti']);
        $this->assertCount(1, $result['errori']);
        $this->assertDatabaseCount('workout_exercises', 1);
    }

    public function test_allenamenti_pianificati_lists_upcoming_workouts_with_completion()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id, 'name' => 'Push-up']);
        $workout = Workout::factory()->create(['user_id' => $user->id, 'workout_date' => now()->addDay()->toDateString()]);
        $workoutExercise = $workout->exercises()->create(['exercise_id' => $exercise->id, 'sets_count' => 2, 'reps_count' => 10, 'position' => 0]);
        $workoutExercise->sets()->create(['set_number' => 1, 'completed' => true]);
        $workoutExercise->sets()->create(['set_number' => 2, 'completed' => false]);

        $executor = app(AiToolExecutor::class);
        $result = $executor->execute('allenamenti_pianificati', [], $user);

        $this->assertCount(1, $result['allenamenti']);
        $this->assertSame('Push-up', $result['allenamenti'][0]['esercizi'][0]['nome']);
        $this->assertSame(1, $result['allenamenti'][0]['esercizi'][0]['serie_completate']);
    }
}
