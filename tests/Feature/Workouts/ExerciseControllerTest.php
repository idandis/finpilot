<?php

namespace Tests\Feature\Workouts;

use App\Models\Exercise;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExerciseControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_create_an_exercise()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('exercises.store'), [
            'name' => 'Squat',
            'category' => 'gambe',
            'requires_equipment' => '0',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('exercises', [
            'user_id' => $user->id,
            'name' => 'Squat',
            'category' => 'gambe',
            'requires_equipment' => false,
        ]);
    }

    public function test_creating_an_exercise_with_an_invalid_category_fails_validation()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('exercises.store'), [
            'name' => 'Esercizio misterioso',
            'category' => 'non-existent',
        ]);

        $response->assertSessionHasErrors('category');
    }

    public function test_a_user_can_update_their_own_exercise()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id, 'name' => 'Vecchio nome']);

        $response = $this->actingAs($user)->patch(route('exercises.update', $exercise), [
            'name' => 'Nuovo nome',
            'category' => 'braccia',
            'requires_equipment' => '1',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('exercises', ['id' => $exercise->id, 'name' => 'Nuovo nome', 'requires_equipment' => true]);
    }

    public function test_a_user_cannot_update_another_users_exercise()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => User::factory(), 'name' => 'Non mio']);

        $response = $this->actingAs($user)->patch(route('exercises.update', $exercise), [
            'name' => 'Rubato',
            'category' => 'braccia',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseHas('exercises', ['id' => $exercise->id, 'name' => 'Non mio']);
    }

    public function test_a_user_can_delete_their_own_exercise()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->delete(route('exercises.destroy', $exercise));

        $response->assertRedirect();
        $this->assertDatabaseMissing('exercises', ['id' => $exercise->id]);
    }

    public function test_a_user_cannot_delete_another_users_exercise()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => User::factory()]);

        $response = $this->actingAs($user)->delete(route('exercises.destroy', $exercise));

        $response->assertForbidden();
        $this->assertDatabaseHas('exercises', ['id' => $exercise->id]);
    }
}
