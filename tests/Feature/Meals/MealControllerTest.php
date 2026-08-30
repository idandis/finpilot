<?php

namespace Tests\Feature\Meals;

use App\Models\Dish;
use App\Models\DishIngredient;
use App\Models\Meal;
use App\Models\ShoppingList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Smalot\PdfParser\Parser;
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

    public function test_the_board_exposes_the_users_dishes_and_the_available_categories()
    {
        $user = User::factory()->create();
        Dish::factory()->create(['user_id' => $user->id, 'name' => 'Pizza', 'category' => 'pasta_riso']);
        Dish::factory()->create(['user_id' => User::factory(), 'name' => 'Non mio']);

        $response = $this->actingAs($user)->get(route('meals.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('dishes', 1)
            ->where('dishes.0.name', 'Pizza')
            ->has('dishCategories.pasta_riso')
        );
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

    public function test_a_meal_can_be_created_with_a_category()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('meals.store'), [
            'title' => 'Bistecca ai ferri',
            'meal_date' => today()->toDateString(),
            'meal_type' => 'dinner',
            'category' => 'carne',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('meals', ['title' => 'Bistecca ai ferri', 'category' => 'carne']);
    }

    public function test_an_empty_category_is_treated_as_none()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('meals.store'), [
            'title' => 'Senza categoria',
            'meal_date' => today()->toDateString(),
            'meal_type' => 'lunch',
            'category' => '',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('meals', ['title' => 'Senza categoria', 'category' => null]);
    }

    public function test_creating_a_meal_with_an_invalid_category_fails_validation()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('meals.store'), [
            'title' => 'Piatto misterioso',
            'meal_date' => today()->toDateString(),
            'meal_type' => 'lunch',
            'category' => 'non-existent',
        ]);

        $response->assertSessionHasErrors('category');
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

    public function test_a_user_can_edit_a_meal_they_planned()
    {
        $user = User::factory()->create();
        $monday = Carbon::today()->startOfWeek(Carbon::MONDAY);
        $meal = Meal::factory()->create([
            'user_id' => $user->id,
            'meal_date' => $monday,
            'meal_type' => 'lunch',
            'title' => 'Pasta',
            'description' => null,
            'category' => null,
            'position' => 3,
        ]);

        $response = $this->actingAs($user)->patch(route('meals.update', $meal), [
            'title' => 'Pasta al pesto',
            'description' => 'Con i pinoli',
            'category' => 'pasta_riso',
        ]);

        $response->assertRedirect();
        $meal->refresh();
        $this->assertSame('Pasta al pesto', $meal->title);
        $this->assertSame('Con i pinoli', $meal->description);
        $this->assertSame('pasta_riso', $meal->category);
        // Editing leaves where the meal sits in the week alone.
        $this->assertSame($monday->toDateString(), $meal->meal_date->toDateString());
        $this->assertSame('lunch', $meal->meal_type);
        $this->assertSame(3, $meal->position);
    }

    public function test_editing_a_meal_can_clear_its_category_and_change_its_cook()
    {
        $owner = User::factory()->create();
        $mate = User::factory()->create();
        $owner->mealPlanMembers()->attach($mate->id);
        $meal = Meal::factory()->create([
            'user_id' => $owner->id,
            'meal_date' => Carbon::today(),
            'meal_type' => 'dinner',
            'category' => 'carne',
            'assigned_to_user_id' => $owner->id,
        ]);

        $this->actingAs($mate)->patch(route('meals.update', $meal), [
            'title' => 'Insalatona',
            'category' => '',
            'assigned_to_user_id' => $mate->id,
        ]);

        $meal->refresh();
        $this->assertNull($meal->category);
        $this->assertSame($mate->id, $meal->assigned_to_user_id);
    }

    public function test_editing_a_meal_requires_a_title_and_a_valid_category()
    {
        $user = User::factory()->create();
        $meal = Meal::factory()->create(['user_id' => $user->id, 'title' => 'Pasta']);

        $this->actingAs($user)->patch(route('meals.update', $meal), ['title' => ''])
            ->assertSessionHasErrors('title');

        $this->actingAs($user)->patch(route('meals.update', $meal), ['title' => 'Pasta', 'category' => 'inesistente'])
            ->assertSessionHasErrors('category');

        $this->assertSame('Pasta', $meal->fresh()->title);
    }

    public function test_a_meal_cannot_be_edited_to_a_cook_outside_its_plan()
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $meal = Meal::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($owner)->patch(route('meals.update', $meal), [
            'title' => 'Pasta',
            'assigned_to_user_id' => $stranger->id,
        ]);

        $response->assertSessionHasErrors('assigned_to_user_id');
        $this->assertNull($meal->fresh()->assigned_to_user_id);
    }

    public function test_a_user_cannot_edit_a_meal_on_a_plan_they_are_not_on()
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $meal = Meal::factory()->create(['user_id' => $owner->id, 'title' => 'Pasta']);

        $response = $this->actingAs($stranger)->patch(route('meals.update', $meal), ['title' => 'Rubato']);

        $response->assertForbidden();
        $this->assertSame('Pasta', $meal->fresh()->title);
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

    public function test_guests_cannot_download_the_pdf()
    {
        $response = $this->get(route('meals.pdf'));

        $response->assertRedirect(route('login'));
    }

    public function test_a_user_can_download_a_pdf_of_the_current_week()
    {
        $user = User::factory()->create();
        $monday = today()->startOfWeek(Carbon::MONDAY);
        Meal::factory()->create(['user_id' => $user->id, 'meal_date' => $monday, 'meal_type' => 'lunch', 'title' => 'Pasta al pomodoro', 'category' => 'pasta_riso']);

        $response = $this->actingAs($user)->get(route('meals.pdf'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_the_pdf_only_includes_the_requested_weeks_meals()
    {
        $user = User::factory()->create();
        $monday = today()->startOfWeek(Carbon::MONDAY);
        Meal::factory()->create(['user_id' => $user->id, 'meal_date' => $monday, 'title' => 'Questa settimana']);
        Meal::factory()->create(['user_id' => $user->id, 'meal_date' => $monday->copy()->subWeek(), 'title' => 'Settimana scorsa']);

        $response = $this->actingAs($user)->get(route('meals.pdf'));

        $response->assertOk();

        $text = (new Parser)->parseContent($response->getContent())->getText();
        $this->assertStringContainsString('Questa settimana', $text);
        $this->assertStringNotContainsString('Settimana scorsa', $text);
    }

    public function test_the_pdf_names_whoever_is_cooking_each_meal()
    {
        $owner = User::factory()->create();
        $mate = User::factory()->create(['name' => 'Nicolas Picco']);
        $owner->mealPlanMembers()->attach($mate->id);
        $monday = today()->startOfWeek(Carbon::MONDAY);
        Meal::factory()->create([
            'user_id' => $owner->id,
            'meal_date' => $monday,
            'meal_type' => 'lunch',
            'title' => 'Pasta al pesto',
            'assigned_to_user_id' => $mate->id,
        ]);
        Meal::factory()->create([
            'user_id' => $owner->id,
            'meal_date' => $monday,
            'meal_type' => 'dinner',
            'title' => 'Minestrone',
            'assigned_to_user_id' => null,
        ]);

        $response = $this->actingAs($owner)->get(route('meals.pdf'));

        $response->assertOk();

        $text = (new Parser)->parseContent($response->getContent())->getText();
        $this->assertStringContainsString('Cucina Nicolas Picco', $text);
        // A meal nobody is cooking says nothing at all.
        $this->assertSame(1, substr_count($text, 'Cucina'));
    }

    public function test_a_user_only_sees_their_own_meals_in_the_pdf()
    {
        $user = User::factory()->create();
        $monday = today()->startOfWeek(Carbon::MONDAY);
        Meal::factory()->create(['user_id' => User::factory(), 'meal_date' => $monday, 'title' => 'Pasto di un altro']);

        $response = $this->actingAs($user)->get(route('meals.pdf'));

        $response->assertOk();

        $text = (new Parser)->parseContent($response->getContent())->getText();
        $this->assertStringNotContainsString('Pasto di un altro', $text);
    }

    public function test_creating_a_meal_from_a_dish_persists_the_dish_id()
    {
        $user = User::factory()->create();
        $dish = Dish::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post(route('meals.store'), [
            'title' => $dish->name,
            'meal_date' => today()->toDateString(),
            'meal_type' => 'lunch',
            'dish_id' => $dish->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('meals', ['title' => $dish->name, 'dish_id' => $dish->id]);
    }

    public function test_a_user_cannot_link_a_meal_to_another_users_dish()
    {
        $user = User::factory()->create();
        $dish = Dish::factory()->create(['user_id' => User::factory()]);

        $response = $this->actingAs($user)->post(route('meals.store'), [
            'title' => 'Piatto altrui',
            'meal_date' => today()->toDateString(),
            'meal_type' => 'lunch',
            'dish_id' => $dish->id,
        ]);

        $response->assertSessionHasErrors('dish_id');
    }

    public function test_guests_cannot_generate_a_shopping_list()
    {
        $response = $this->post(route('meals.generate-shopping-list'));

        $response->assertRedirect(route('login'));
    }

    public function test_generating_a_shopping_list_uses_the_dishs_ingredients()
    {
        $user = User::factory()->create();
        $monday = today()->startOfWeek(Carbon::MONDAY);
        $dish = Dish::factory()->create(['user_id' => $user->id, 'name' => 'Pasta al pomodoro']);
        DishIngredient::factory()->create(['dish_id' => $dish->id, 'name' => 'Pasta', 'category' => 'pasta_riso_cereali']);
        DishIngredient::factory()->create(['dish_id' => $dish->id, 'name' => 'Pomodoro', 'category' => 'verdura']);
        Meal::factory()->create(['user_id' => $user->id, 'dish_id' => $dish->id, 'meal_date' => $monday, 'title' => $dish->name]);

        $response = $this->actingAs($user)->post(route('meals.generate-shopping-list'));

        $list = ShoppingList::query()->where('user_id', $user->id)->firstOrFail();
        $response->assertRedirect(route('shopping-lists.show', $list));
        $this->assertDatabaseHas('shopping_list_items', ['shopping_list_id' => $list->id, 'name' => 'Pasta', 'category' => 'pasta_riso_cereali']);
        $this->assertDatabaseHas('shopping_list_items', ['shopping_list_id' => $list->id, 'name' => 'Pomodoro', 'category' => 'verdura']);
    }

    public function test_generating_a_shopping_list_falls_back_to_the_meal_title_without_a_dish()
    {
        $user = User::factory()->create();
        $monday = today()->startOfWeek(Carbon::MONDAY);
        Meal::factory()->create(['user_id' => $user->id, 'dish_id' => null, 'meal_date' => $monday, 'title' => 'Avanzi di ieri', 'category' => null]);

        $response = $this->actingAs($user)->post(route('meals.generate-shopping-list'));

        $list = ShoppingList::query()->where('user_id', $user->id)->firstOrFail();
        $response->assertRedirect(route('shopping-lists.show', $list));
        $this->assertDatabaseHas('shopping_list_items', ['shopping_list_id' => $list->id, 'name' => 'Avanzi di ieri', 'category' => 'altro']);
    }

    public function test_generating_a_shopping_list_maps_a_meals_own_carne_category_to_the_grocery_one()
    {
        $user = User::factory()->create();
        $monday = today()->startOfWeek(Carbon::MONDAY);
        Meal::factory()->create(['user_id' => $user->id, 'dish_id' => null, 'meal_date' => $monday, 'title' => 'Bistecca', 'category' => 'carne']);

        $response = $this->actingAs($user)->post(route('meals.generate-shopping-list'));

        $list = ShoppingList::query()->where('user_id', $user->id)->firstOrFail();
        $response->assertRedirect(route('shopping-lists.show', $list));
        $this->assertDatabaseHas('shopping_list_items', ['shopping_list_id' => $list->id, 'name' => 'Bistecca', 'category' => 'carne']);
    }

    public function test_generating_a_shopping_list_deduplicates_repeated_ingredients()
    {
        $user = User::factory()->create();
        $monday = today()->startOfWeek(Carbon::MONDAY);
        $dish = Dish::factory()->create(['user_id' => $user->id, 'name' => 'Pasta al pomodoro']);
        DishIngredient::factory()->create(['dish_id' => $dish->id, 'name' => 'Pasta', 'category' => 'pasta_riso_cereali']);
        Meal::factory()->create(['user_id' => $user->id, 'dish_id' => $dish->id, 'meal_date' => $monday, 'meal_type' => 'lunch', 'title' => $dish->name]);
        Meal::factory()->create(['user_id' => $user->id, 'dish_id' => $dish->id, 'meal_date' => $monday->copy()->addDays(3), 'meal_type' => 'dinner', 'title' => $dish->name]);

        $this->actingAs($user)->post(route('meals.generate-shopping-list'));

        $list = ShoppingList::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame(1, $list->items()->where('name', 'Pasta')->count());
    }

    public function test_generating_a_shopping_list_does_nothing_for_an_empty_week()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('meals.generate-shopping-list'));

        $response->assertRedirect();
        $this->assertDatabaseCount('shopping_lists', 0);
    }

    public function test_generating_a_shopping_list_only_includes_the_users_own_meals()
    {
        $user = User::factory()->create();
        $monday = today()->startOfWeek(Carbon::MONDAY);
        Meal::factory()->create(['user_id' => User::factory(), 'dish_id' => null, 'meal_date' => $monday, 'title' => 'Pasto di un altro', 'category' => null]);
        Meal::factory()->create(['user_id' => $user->id, 'dish_id' => null, 'meal_date' => $monday, 'title' => 'Il mio pasto', 'category' => null]);

        $this->actingAs($user)->post(route('meals.generate-shopping-list'));

        $list = ShoppingList::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertDatabaseHas('shopping_list_items', ['shopping_list_id' => $list->id, 'name' => 'Il mio pasto']);
        $this->assertDatabaseMissing('shopping_list_items', ['shopping_list_id' => $list->id, 'name' => 'Pasto di un altro']);
    }

    public function test_a_plan_can_be_shared_by_email_and_shows_up_in_the_other_users_switcher()
    {
        $owner = User::factory()->create(['name' => 'Iana Longo']);
        $mate = User::factory()->create(['email' => 'mate@example.com']);

        $this->actingAs($owner)->post(route('meal-plan.members.store'), ['email' => 'mate@example.com']);

        $this->assertTrue($owner->mealPlanMembers()->whereKey($mate->id)->exists());

        $this->actingAs($mate)->get(route('meals.index'))
            ->assertInertia(fn ($page) => $page
                ->has('plans', 2)
                ->where('plans.0.name', 'I miei pasti')
                ->where('plans.1.name', 'Iana Longo')
                ->where('plans.1.is_shared', true)
                ->where('plan.is_owner', true)
            );
    }

    public function test_a_member_sees_and_works_on_the_shared_week()
    {
        $owner = User::factory()->create();
        $mate = User::factory()->create();
        $owner->mealPlanMembers()->attach($mate->id);
        $monday = Carbon::today()->startOfWeek(Carbon::MONDAY);
        $ownersMeal = Meal::factory()->create([
            'user_id' => $owner->id,
            'title' => "Dell'owner",
            'meal_date' => $monday,
            'meal_type' => 'lunch',
        ]);

        $this->actingAs($mate)->get(route('meals.index', ['plan' => $owner->id]))
            ->assertInertia(fn ($page) => $page
                ->has('meals', 1)
                ->where('meals.0.title', "Dell'owner")
                ->where('plan.is_owner', false)
                ->has('plan.people', 2)
            );

        $this->actingAs($mate)->post(route('meals.store'), [
            'title' => 'Del membro',
            'meal_date' => $monday->toDateString(),
            'meal_type' => 'dinner',
            'plan_user_id' => $owner->id,
        ]);
        $this->assertDatabaseHas('meals', ['title' => 'Del membro', 'user_id' => $owner->id]);

        $this->actingAs($mate)->patch(route('meals.move', $ownersMeal), [
            'meal_date' => $monday->copy()->addDay()->toDateString(),
            'meal_type' => 'dinner',
        ]);
        $this->assertSame('dinner', $ownersMeal->fresh()->meal_type);

        $this->actingAs($mate)->delete(route('meals.destroy', $ownersMeal));
        $this->assertDatabaseMissing('meals', ['id' => $ownersMeal->id]);
    }

    public function test_a_plan_the_user_was_not_invited_to_falls_back_to_their_own()
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $monday = Carbon::today()->startOfWeek(Carbon::MONDAY);
        $meal = Meal::factory()->create(['user_id' => $owner->id, 'meal_date' => $monday, 'meal_type' => 'lunch']);

        $this->actingAs($stranger)->get(route('meals.index', ['plan' => $owner->id]))
            ->assertInertia(fn ($page) => $page
                ->where('plan.id', $stranger->id)
                ->where('plan.is_owner', true)
                ->has('meals', 0)
            );

        $this->actingAs($stranger)->patch(route('meals.move', $meal), [
            'meal_date' => $monday->toDateString(),
            'meal_type' => 'dinner',
        ])->assertForbidden();
        $this->actingAs($stranger)->delete(route('meals.destroy', $meal))->assertForbidden();
    }

    public function test_a_meal_added_to_a_plan_the_user_cannot_reach_is_rejected()
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();

        $response = $this->actingAs($stranger)->post(route('meals.store'), [
            'title' => 'Intruso',
            'meal_date' => Carbon::today()->toDateString(),
            'meal_type' => 'lunch',
            'plan_user_id' => $owner->id,
        ]);

        $response->assertSessionHasErrors('plan_user_id');
        $this->assertDatabaseCount('meals', 0);
    }

    public function test_a_meal_can_be_assigned_to_anyone_on_its_plan_and_unassigned()
    {
        $owner = User::factory()->create();
        $mate = User::factory()->create(['name' => 'Nicolas Picco']);
        $owner->mealPlanMembers()->attach($mate->id);
        $monday = Carbon::today()->startOfWeek(Carbon::MONDAY);
        $meal = Meal::factory()->create(['user_id' => $owner->id, 'meal_date' => $monday, 'meal_type' => 'lunch']);

        $this->actingAs($mate)->patch(route('meals.assign', $meal), ['assigned_to_user_id' => $mate->id]);
        $this->assertSame($mate->id, $meal->fresh()->assigned_to_user_id);

        $this->actingAs($owner)->get(route('meals.index'))
            ->assertInertia(fn ($page) => $page->where('meals.0.assignee.name', 'Nicolas Picco'));

        $this->actingAs($owner)->patch(route('meals.assign', $meal), ['assigned_to_user_id' => null]);
        $this->assertNull($meal->fresh()->assigned_to_user_id);
    }

    public function test_a_meal_cannot_be_assigned_to_someone_outside_its_plan()
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $meal = Meal::factory()->create(['user_id' => $owner->id, 'meal_date' => Carbon::today(), 'meal_type' => 'lunch']);

        $response = $this->actingAs($owner)->patch(route('meals.assign', $meal), ['assigned_to_user_id' => $stranger->id]);

        $response->assertSessionHasErrors('assigned_to_user_id');
        $this->assertNull($meal->fresh()->assigned_to_user_id);
    }

    public function test_an_unknown_email_or_a_duplicate_cannot_be_invited_to_a_plan()
    {
        $owner = User::factory()->create(['email' => 'owner@example.com']);
        $mate = User::factory()->create(['email' => 'mate@example.com']);
        $owner->mealPlanMembers()->attach($mate->id);

        $this->actingAs($owner)->post(route('meal-plan.members.store'), ['email' => 'nessuno@example.com'])
            ->assertSessionHasErrors('email');
        $this->actingAs($owner)->post(route('meal-plan.members.store'), ['email' => 'owner@example.com'])
            ->assertSessionHasErrors('email');
        $this->actingAs($owner)->post(route('meal-plan.members.store'), ['email' => 'mate@example.com'])
            ->assertSessionHasErrors('email');

        $this->assertSame(1, $owner->mealPlanMembers()->count());
    }

    public function test_removing_someone_from_a_plan_frees_the_meals_they_were_cooking()
    {
        $owner = User::factory()->create();
        $mate = User::factory()->create();
        $owner->mealPlanMembers()->attach($mate->id);
        $meal = Meal::factory()->create([
            'user_id' => $owner->id,
            'assigned_to_user_id' => $mate->id,
            'meal_date' => Carbon::today(),
            'meal_type' => 'lunch',
        ]);

        $this->actingAs($owner)->delete(route('meal-plan.members.destroy', [$owner, $mate]));

        $this->assertSame(0, $owner->mealPlanMembers()->count());
        $this->assertNull($meal->fresh()->assigned_to_user_id);
    }

    public function test_a_member_can_leave_a_plan_but_cannot_remove_anyone_else()
    {
        $owner = User::factory()->create();
        $mate = User::factory()->create();
        $other = User::factory()->create();
        $owner->mealPlanMembers()->attach([$mate->id, $other->id]);

        $this->actingAs($mate)->delete(route('meal-plan.members.destroy', [$owner, $other]))
            ->assertForbidden();

        $response = $this->actingAs($mate)->delete(route('meal-plan.members.destroy', [$owner, $mate]));

        $response->assertRedirect(route('meals.index'));
        $this->assertFalse($owner->mealPlanMembers()->whereKey($mate->id)->exists());
        $this->assertTrue($owner->mealPlanMembers()->whereKey($other->id)->exists());
    }

    public function test_the_owner_cannot_be_removed_from_their_own_plan()
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)->delete(route('meal-plan.members.destroy', [$owner, $owner]))
            ->assertForbidden();
    }

    public function test_the_shopping_list_is_generated_from_the_plan_being_shown()
    {
        $owner = User::factory()->create();
        $mate = User::factory()->create();
        $owner->mealPlanMembers()->attach($mate->id);
        $monday = Carbon::today()->startOfWeek(Carbon::MONDAY);
        Meal::factory()->create(['user_id' => $owner->id, 'title' => 'Pollo', 'meal_date' => $monday, 'meal_type' => 'lunch']);
        Meal::factory()->create(['user_id' => $mate->id, 'title' => 'Solo mio', 'meal_date' => $monday, 'meal_type' => 'lunch']);

        $this->actingAs($mate)->post(route('meals.generate-shopping-list', [
            'date' => $monday->toDateString(),
            'plan' => $owner->id,
        ]));

        $list = ShoppingList::query()->where('user_id', $mate->id)->sole();
        $this->assertSame(['Pollo'], $list->items()->pluck('name')->all());
    }
}
