<?php

namespace Tests\Feature\Meals;

use App\Models\Meal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MealControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('meals.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_the_board_only_shows_the_current_weeks_meals()
    {
        $user = User::factory()->create();
        $monday = today()->startOfWeek(Carbon::MONDAY);
        Meal::factory()->create(['user_id' => $user->id, 'meal_date' => $monday, 'title' => 'Questa settimana']);
        Meal::factory()->create(['user_id' => $user->id, 'meal_date' => $monday->copy()->subWeek(), 'title' => 'Settimana scorsa']);
        Meal::factory()->create(['user_id' => $user->id, 'meal_date' => $monday->copy()->addWeek(), 'title' => 'Prossima settimana']);

        $response = $this->actingAs($user)->get(route('meals.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('meals', 1)
            ->where('meals.0.title', 'Questa settimana')
            ->where('weekStart', $monday->toDateString())
        );
    }

    public function test_another_week_can_be_browsed_via_the_date_query_param()
    {
        $user = User::factory()->create();
        $targetMonday = today()->startOfWeek(Carbon::MONDAY)->addWeeks(2);
        Meal::factory()->create(['user_id' => $user->id, 'meal_date' => $targetMonday->copy()->addDays(2), 'title' => 'Nella settimana richiesta']);
        Meal::factory()->create(['user_id' => $user->id, 'meal_date' => today(), 'title' => 'Questa settimana']);

        $response = $this->actingAs($user)->get(route('meals.index', ['date' => $targetMonday->copy()->addDays(4)->toDateString()]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('meals', 1)
            ->where('meals.0.title', 'Nella settimana richiesta')
            ->where('weekStart', $targetMonday->toDateString())
            ->where('today', today()->toDateString())
        );
    }

    public function test_a_non_monday_date_normalizes_to_that_weeks_monday()
    {
        $user = User::factory()->create();
        $wednesday = today()->startOfWeek(Carbon::MONDAY)->addWeeks(3)->addDays(2);

        $response = $this->actingAs($user)->get(route('meals.index', ['date' => $wednesday->toDateString()]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->where('weekStart', $wednesday->copy()->startOfWeek(Carbon::MONDAY)->toDateString()));
    }

    public function test_a_malformed_date_falls_back_to_the_current_week()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('meals.index', ['date' => 'not-a-date']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->where('weekStart', today()->startOfWeek(Carbon::MONDAY)->toDateString()));
    }

    public function test_a_user_only_sees_their_own_meals()
    {
        $user = User::factory()->create();
        Meal::factory()->create(['user_id' => $user->id, 'meal_date' => today()]);
        Meal::factory()->create(['user_id' => User::factory(), 'meal_date' => today()]);

        $response = $this->actingAs($user)->get(route('meals.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->has('meals', 1));
    }

    public function test_a_user_can_create_a_meal()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('meals.store'), [
            'title' => 'Pasta al pomodoro',
            'description' => 'Con basilico fresco',
            'meal_date' => today()->toDateString(),
            'meal_type' => 'lunch',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('meals', [
            'user_id' => $user->id,
            'title' => 'Pasta al pomodoro',
            'description' => 'Con basilico fresco',
            'meal_type' => 'lunch',
            'position' => 0,
        ]);
    }

    public function test_a_meal_can_be_created_without_a_description()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('meals.store'), [
            'title' => 'Solo titolo',
            'meal_date' => today()->toDateString(),
            'meal_type' => 'dinner',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('meals', ['title' => 'Solo titolo', 'description' => null]);
    }

    public function test_creating_a_meal_requires_a_title_date_and_valid_type()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('meals.store'), [
            'title' => '',
            'meal_date' => '',
            'meal_type' => 'brunch',
        ]);

        $response->assertSessionHasErrors(['title', 'meal_date', 'meal_type']);
    }

    public function test_new_meals_are_appended_to_the_end_of_their_day_and_slot()
    {
        $user = User::factory()->create();
        Meal::factory()->create(['user_id' => $user->id, 'meal_date' => today(), 'meal_type' => 'lunch', 'position' => 0]);
        Meal::factory()->create(['user_id' => $user->id, 'meal_date' => today(), 'meal_type' => 'lunch', 'position' => 1]);

        $this->actingAs($user)->post(route('meals.store'), [
            'title' => 'Terzo',
            'meal_date' => today()->toDateString(),
            'meal_type' => 'lunch',
        ]);

        $this->assertDatabaseHas('meals', ['title' => 'Terzo', 'position' => 2]);
    }

    public function test_a_user_can_move_their_own_meal_to_another_slot_and_day()
    {
        $user = User::factory()->create();
        $meal = Meal::factory()->create(['user_id' => $user->id, 'meal_date' => today(), 'meal_type' => 'lunch']);
        $targetDate = today()->addDay();

        $response = $this->actingAs($user)->patch(route('meals.move', $meal), [
            'meal_date' => $targetDate->toDateString(),
            'meal_type' => 'dinner',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('meals', [
            'id' => $meal->id,
            'meal_date' => $targetDate->toDateString().' 00:00:00',
            'meal_type' => 'dinner',
        ]);
    }

    public function test_moving_a_meal_appends_it_to_the_end_of_the_target_slot()
    {
        $user = User::factory()->create();
        $targetDate = today()->addDay();
        Meal::factory()->create(['user_id' => $user->id, 'meal_date' => $targetDate, 'meal_type' => 'dinner', 'position' => 0]);
        Meal::factory()->create(['user_id' => $user->id, 'meal_date' => $targetDate, 'meal_type' => 'dinner', 'position' => 1]);
        $meal = Meal::factory()->create(['user_id' => $user->id, 'meal_date' => today(), 'meal_type' => 'lunch', 'position' => 0]);

        $this->actingAs($user)->patch(route('meals.move', $meal), [
            'meal_date' => $targetDate->toDateString(),
            'meal_type' => 'dinner',
        ]);

        $this->assertDatabaseHas('meals', ['id' => $meal->id, 'meal_type' => 'dinner', 'position' => 2]);
    }

    public function test_moving_requires_a_valid_date_and_type()
    {
        $user = User::factory()->create();
        $meal = Meal::factory()->create(['user_id' => $user->id, 'meal_date' => today(), 'meal_type' => 'lunch']);

        $response = $this->actingAs($user)->patch(route('meals.move', $meal), [
            'meal_date' => today()->toDateString(),
            'meal_type' => 'brunch',
        ]);

        $response->assertSessionHasErrors('meal_type');
    }

    public function test_a_user_cannot_move_another_users_meal()
    {
        $user = User::factory()->create();
        $meal = Meal::factory()->create(['user_id' => User::factory(), 'meal_date' => today(), 'meal_type' => 'lunch']);

        $response = $this->actingAs($user)->patch(route('meals.move', $meal), [
            'meal_date' => today()->toDateString(),
            'meal_type' => 'dinner',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseHas('meals', ['id' => $meal->id, 'meal_type' => 'lunch']);
    }

    public function test_a_user_can_delete_their_own_meal()
    {
        $user = User::factory()->create();
        $meal = Meal::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->delete(route('meals.destroy', $meal));

        $response->assertRedirect();
        $this->assertDatabaseMissing('meals', ['id' => $meal->id]);
    }

    public function test_a_user_cannot_delete_another_users_meal()
    {
        $user = User::factory()->create();
        $meal = Meal::factory()->create(['user_id' => User::factory()]);

        $response = $this->actingAs($user)->delete(route('meals.destroy', $meal));

        $response->assertForbidden();
        $this->assertDatabaseHas('meals', ['id' => $meal->id]);
    }
}
