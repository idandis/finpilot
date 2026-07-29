<?php

namespace Tests\Feature\Workouts;

use App\Models\Exercise;
use App\Models\User;
use App\Models\Workout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class WorkoutControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('workouts.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_the_board_only_shows_the_current_weeks_workouts()
    {
        $user = User::factory()->create();
        $monday = today()->startOfWeek(Carbon::MONDAY);
        Workout::factory()->create(['user_id' => $user->id, 'workout_date' => $monday, 'title' => 'Questa settimana']);
        Workout::factory()->create(['user_id' => $user->id, 'workout_date' => $monday->copy()->subWeek(), 'title' => 'Settimana scorsa']);
        Workout::factory()->create(['user_id' => $user->id, 'workout_date' => $monday->copy()->addWeek(), 'title' => 'Prossima settimana']);

        $response = $this->actingAs($user)->get(route('workouts.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('workouts', 1)
            ->where('workouts.0.title', 'Questa settimana')
            ->where('weekStart', $monday->toDateString())
        );
    }

    public function test_a_user_only_sees_their_own_workouts()
    {
        $user = User::factory()->create();
        Workout::factory()->create(['user_id' => $user->id, 'workout_date' => today()]);
        Workout::factory()->create(['user_id' => User::factory(), 'workout_date' => today()]);

        $response = $this->actingAs($user)->get(route('workouts.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->has('workouts', 1));
    }

    public function test_the_board_exposes_the_users_exercises_and_the_available_categories()
    {
        $user = User::factory()->create();
        Exercise::factory()->create(['user_id' => $user->id, 'name' => 'Squat', 'category' => 'gambe']);
        Exercise::factory()->create(['user_id' => User::factory(), 'name' => 'Non mio']);

        $response = $this->actingAs($user)->get(route('workouts.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('exercises', 1)
            ->where('exercises.0.name', 'Squat')
            ->has('exerciseCategories.gambe')
        );
    }

    public function test_a_user_can_create_a_workout_with_exercises()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id, 'name' => 'Squat']);

        $response = $this->actingAs($user)->post(route('workouts.store'), [
            'workout_date' => today()->toDateString(),
            'exercises' => [
                ['exercise_id' => $exercise->id, 'sets_count' => 3, 'reps_count' => 10],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('workouts', ['user_id' => $user->id, 'workout_date' => today()->toDateString().' 00:00:00']);
        $this->assertDatabaseHas('workout_exercises', ['exercise_id' => $exercise->id, 'sets_count' => 3, 'reps_count' => 10]);
        $this->assertDatabaseCount('workout_sets', 3);
    }

    public function test_adding_exercises_to_an_existing_days_workout_reuses_it()
    {
        $user = User::factory()->create();
        $exerciseOne = Exercise::factory()->create(['user_id' => $user->id]);
        $exerciseTwo = Exercise::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)->post(route('workouts.store'), [
            'workout_date' => today()->toDateString(),
            'exercises' => [['exercise_id' => $exerciseOne->id, 'sets_count' => 2, 'reps_count' => 8]],
        ]);

        $this->actingAs($user)->post(route('workouts.store'), [
            'workout_date' => today()->toDateString(),
            'exercises' => [['exercise_id' => $exerciseTwo->id, 'sets_count' => 3, 'reps_count' => 12]],
        ]);

        $this->assertDatabaseCount('workouts', 1);
        $this->assertDatabaseHas('workout_exercises', ['exercise_id' => $exerciseOne->id, 'position' => 0]);
        $this->assertDatabaseHas('workout_exercises', ['exercise_id' => $exerciseTwo->id, 'position' => 1]);
    }

    public function test_creating_a_workout_requires_at_least_one_exercise()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('workouts.store'), [
            'workout_date' => today()->toDateString(),
            'exercises' => [],
        ]);

        $response->assertSessionHasErrors('exercises');
    }

    public function test_a_user_cannot_use_another_users_exercise()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => User::factory()]);

        $response = $this->actingAs($user)->post(route('workouts.store'), [
            'workout_date' => today()->toDateString(),
            'exercises' => [['exercise_id' => $exercise->id, 'sets_count' => 3, 'reps_count' => 10]],
        ]);

        $response->assertSessionHasErrors('exercises.0.exercise_id');
    }

    public function test_a_user_can_toggle_a_sets_completion()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);
        $workout = Workout::factory()->create(['user_id' => $user->id]);
        $workoutExercise = $workout->exercises()->create(['exercise_id' => $exercise->id, 'sets_count' => 1, 'reps_count' => 10, 'position' => 0]);
        $set = $workoutExercise->sets()->create(['set_number' => 1]);

        $response = $this->actingAs($user)->patch(route('workout-sets.toggle', $set));

        $response->assertRedirect();
        $this->assertDatabaseHas('workout_sets', ['id' => $set->id, 'completed' => true]);

        $this->actingAs($user)->patch(route('workout-sets.toggle', $set));
        $this->assertDatabaseHas('workout_sets', ['id' => $set->id, 'completed' => false]);
    }

    public function test_a_user_cannot_toggle_another_users_set()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $otherUser->id]);
        $workout = Workout::factory()->create(['user_id' => $otherUser->id]);
        $workoutExercise = $workout->exercises()->create(['exercise_id' => $exercise->id, 'sets_count' => 1, 'reps_count' => 10, 'position' => 0]);
        $set = $workoutExercise->sets()->create(['set_number' => 1]);

        $response = $this->actingAs($user)->patch(route('workout-sets.toggle', $set));

        $response->assertForbidden();
        $this->assertDatabaseHas('workout_sets', ['id' => $set->id, 'completed' => false]);
    }

    public function test_a_user_can_view_their_own_workout()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id, 'name' => 'Panca piana']);
        $workout = Workout::factory()->create(['user_id' => $user->id]);
        $workoutExercise = $workout->exercises()->create(['exercise_id' => $exercise->id, 'sets_count' => 2, 'reps_count' => 8, 'position' => 0]);
        $workoutExercise->sets()->create(['set_number' => 1]);
        $workoutExercise->sets()->create(['set_number' => 2]);

        $response = $this->actingAs($user)->get(route('workouts.show', $workout));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('workout.exercises.0.exercise_name', 'Panca piana')
            ->has('workout.exercises.0.sets', 2)
        );
    }

    public function test_a_user_cannot_view_another_users_workout()
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => User::factory()]);

        $response = $this->actingAs($user)->get(route('workouts.show', $workout));

        $response->assertForbidden();
    }

    public function test_a_user_can_remove_an_exercise_from_their_workout()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);
        $workout = Workout::factory()->create(['user_id' => $user->id]);
        $workoutExercise = $workout->exercises()->create(['exercise_id' => $exercise->id, 'sets_count' => 1, 'reps_count' => 10, 'position' => 0]);

        $response = $this->actingAs($user)->delete(route('workouts.exercises.destroy', [$workout, $workoutExercise]));

        $response->assertRedirect();
        $this->assertDatabaseMissing('workout_exercises', ['id' => $workoutExercise->id]);
    }

    public function test_a_user_can_delete_their_own_workout()
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->delete(route('workouts.destroy', $workout));

        $response->assertRedirect();
        $this->assertDatabaseMissing('workouts', ['id' => $workout->id]);
    }

    public function test_a_user_cannot_delete_another_users_workout()
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => User::factory()]);

        $response = $this->actingAs($user)->delete(route('workouts.destroy', $workout));

        $response->assertForbidden();
        $this->assertDatabaseHas('workouts', ['id' => $workout->id]);
    }

    public function test_schedule_assigns_a_time_slot()
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $user->id, 'workout_date' => today(), 'scheduled_time' => null]);

        $response = $this->actingAs($user)->patch(route('workouts.schedule', $workout), [
            'workout_date' => today()->toDateString(),
            'scheduled_time' => '18:00',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('workouts', ['id' => $workout->id, 'scheduled_time' => '18:00:00']);
    }

    public function test_schedule_can_move_a_workout_to_a_different_day()
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $user->id, 'workout_date' => today()]);

        $response = $this->actingAs($user)->patch(route('workouts.schedule', $workout), [
            'workout_date' => today()->addDay()->toDateString(),
            'scheduled_time' => '07:30',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('workouts', [
            'id' => $workout->id,
            'workout_date' => today()->addDay()->toDateString().' 00:00:00',
            'scheduled_time' => '07:30:00',
        ]);
    }

    public function test_schedule_is_forbidden_for_another_users_workout()
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => User::factory()]);

        $response = $this->actingAs($user)->patch(route('workouts.schedule', $workout), [
            'workout_date' => today()->toDateString(),
            'scheduled_time' => '18:00',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseHas('workouts', ['id' => $workout->id, 'scheduled_time' => null]);
    }
}
