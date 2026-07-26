<?php

namespace Tests\Feature\Meals;

use App\Models\Dish;
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
