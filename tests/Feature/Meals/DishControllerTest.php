<?php

namespace Tests\Feature\Meals;

use App\Models\Dish;
use App\Models\DishIngredient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DishControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_create_a_dish()
    {
        $response = $this->post(route('dishes.store'), [
            'name' => 'Pizza',
            'category' => 'altro',
        ]);

        $response->assertRedirect(route('login'));
    }

    public function test_a_user_can_create_a_dish()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('dishes.store'), [
            'name' => 'Pizza margherita',
            'description' => 'Pomodoro, mozzarella e basilico',
            'category' => 'pasta_riso',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('dishes', [
            'user_id' => $user->id,
            'name' => 'Pizza margherita',
            'description' => 'Pomodoro, mozzarella e basilico',
            'category' => 'pasta_riso',
        ]);
    }

    public function test_a_dish_can_be_created_without_a_description()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('dishes.store'), [
            'name' => 'Bistecca',
            'category' => 'carne',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('dishes', ['name' => 'Bistecca', 'description' => null]);
    }

    public function test_creating_a_dish_requires_a_name_and_a_valid_category()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('dishes.store'), [
            'name' => '',
            'category' => 'non-existent',
        ]);

        $response->assertSessionHasErrors(['name', 'category']);
    }

    public function test_a_dish_can_be_created_with_ingredients()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('dishes.store'), [
            'name' => 'Pasta al pomodoro',
            'category' => 'pasta_riso',
            'ingredients' => [
                ['name' => 'Pasta', 'category' => 'pasta_riso_cereali'],
                ['name' => 'Pomodoro', 'category' => 'verdura'],
            ],
        ]);

        $response->assertRedirect();
        $dish = Dish::query()->where('name', 'Pasta al pomodoro')->firstOrFail();
        $this->assertDatabaseHas('dish_ingredients', ['dish_id' => $dish->id, 'name' => 'Pasta', 'category' => 'pasta_riso_cereali']);
        $this->assertDatabaseHas('dish_ingredients', ['dish_id' => $dish->id, 'name' => 'Pomodoro', 'category' => 'verdura']);
    }

    public function test_ingredient_rows_without_a_name_are_ignored()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('dishes.store'), [
            'name' => 'Insalata',
            'category' => 'verdure',
            'ingredients' => [
                ['name' => '', 'category' => 'verdura'],
                ['name' => 'Lattuga', 'category' => 'verdura'],
            ],
        ]);

        $response->assertRedirect();
        $dish = Dish::query()->where('name', 'Insalata')->firstOrFail();
        $this->assertSame(1, $dish->ingredients()->count());
        $this->assertDatabaseHas('dish_ingredients', ['dish_id' => $dish->id, 'name' => 'Lattuga']);
    }

    public function test_an_ingredient_without_a_category_falls_back_to_altro()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('dishes.store'), [
            'name' => 'Piatto misto',
            'category' => 'altro',
            'ingredients' => [
                ['name' => 'Sale', 'category' => ''],
            ],
        ]);

        $response->assertRedirect();
        $dish = Dish::query()->where('name', 'Piatto misto')->firstOrFail();
        $this->assertDatabaseHas('dish_ingredients', ['dish_id' => $dish->id, 'name' => 'Sale', 'category' => 'altro']);
    }

    public function test_guests_cannot_update_a_dish()
    {
        $dish = Dish::factory()->create();

        $response = $this->patch(route('dishes.update', $dish), ['name' => 'Nuovo nome', 'category' => 'altro']);

        $response->assertRedirect(route('login'));
    }

    public function test_a_user_can_update_their_own_dish()
    {
        $user = User::factory()->create();
        $dish = Dish::factory()->create(['user_id' => $user->id, 'name' => 'Vecchio nome', 'category' => 'carne']);

        $response = $this->actingAs($user)->patch(route('dishes.update', $dish), [
            'name' => 'Nuovo nome',
            'description' => 'Nuova descrizione',
            'category' => 'pesce',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('dishes', [
            'id' => $dish->id,
            'name' => 'Nuovo nome',
            'description' => 'Nuova descrizione',
            'category' => 'pesce',
        ]);
    }

    public function test_updating_a_dish_replaces_its_ingredients()
    {
        $user = User::factory()->create();
        $dish = Dish::factory()->create(['user_id' => $user->id]);
        $oldIngredient = DishIngredient::factory()->create(['dish_id' => $dish->id, 'name' => 'Vecchio ingrediente']);

        $response = $this->actingAs($user)->patch(route('dishes.update', $dish), [
            'name' => $dish->name,
            'category' => $dish->category,
            'ingredients' => [
                ['name' => 'Nuovo ingrediente', 'category' => 'verdura'],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseMissing('dish_ingredients', ['id' => $oldIngredient->id]);
        $this->assertDatabaseHas('dish_ingredients', ['dish_id' => $dish->id, 'name' => 'Nuovo ingrediente', 'category' => 'verdura']);
        $this->assertSame(1, $dish->ingredients()->count());
    }

    public function test_updating_a_dish_without_ingredients_removes_the_existing_ones()
    {
        $user = User::factory()->create();
        $dish = Dish::factory()->create(['user_id' => $user->id]);
        DishIngredient::factory()->create(['dish_id' => $dish->id]);

        $response = $this->actingAs($user)->patch(route('dishes.update', $dish), [
            'name' => $dish->name,
            'category' => $dish->category,
        ]);

        $response->assertRedirect();
        $this->assertSame(0, $dish->ingredients()->count());
    }

    public function test_a_user_cannot_update_another_users_dish()
    {
        $user = User::factory()->create();
        $dish = Dish::factory()->create(['user_id' => User::factory(), 'name' => 'Originale']);

        $response = $this->actingAs($user)->patch(route('dishes.update', $dish), [
            'name' => 'Modificato',
            'category' => 'altro',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseHas('dishes', ['id' => $dish->id, 'name' => 'Originale']);
    }

    public function test_updating_a_dish_requires_a_name_and_a_valid_category()
    {
        $user = User::factory()->create();
        $dish = Dish::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->patch(route('dishes.update', $dish), [
            'name' => '',
            'category' => 'non-existent',
        ]);

        $response->assertSessionHasErrors(['name', 'category']);
    }

    public function test_a_user_can_delete_their_own_dish()
    {
        $user = User::factory()->create();
        $dish = Dish::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->delete(route('dishes.destroy', $dish));

        $response->assertRedirect();
        $this->assertDatabaseMissing('dishes', ['id' => $dish->id]);
    }

    public function test_a_user_cannot_delete_another_users_dish()
    {
        $user = User::factory()->create();
        $dish = Dish::factory()->create(['user_id' => User::factory()]);

        $response = $this->actingAs($user)->delete(route('dishes.destroy', $dish));

        $response->assertForbidden();
        $this->assertDatabaseHas('dishes', ['id' => $dish->id]);
    }
}
