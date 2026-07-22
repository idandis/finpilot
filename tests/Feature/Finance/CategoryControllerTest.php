<?php

namespace Tests\Feature\Finance;

use App\Models\TransactionCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('categories.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_a_user_sees_system_categories_and_their_own()
    {
        $user = User::factory()->create();
        TransactionCategory::factory()->create(['user_id' => null, 'name' => 'Sistema']);
        TransactionCategory::factory()->create(['user_id' => $user->id, 'name' => 'Mia']);
        TransactionCategory::factory()->create(['user_id' => User::factory(), 'name' => 'Altrui']);

        $response = $this->actingAs($user)->get(route('categories.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->has('categories', 2));
    }

    public function test_a_user_can_create_a_category()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('categories.store'), [
            'name' => 'Animali domestici',
            'color' => '#64748b',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('transaction_categories', [
            'user_id' => $user->id,
            'name' => 'Animali domestici',
        ]);
    }

    public function test_a_user_cannot_create_a_category_with_a_name_that_already_exists()
    {
        $user = User::factory()->create();
        TransactionCategory::factory()->create(['user_id' => null, 'name' => 'Alimentari']);

        $response = $this->actingAs($user)->post(route('categories.store'), [
            'name' => 'Alimentari',
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_a_user_can_update_their_own_category()
    {
        $user = User::factory()->create();
        $category = TransactionCategory::factory()->create(['user_id' => $user->id, 'name' => 'Vecchio nome']);

        $response = $this->actingAs($user)->patch(route('categories.update', $category), [
            'name' => 'Nuovo nome',
            'color' => '#ff0000',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('transaction_categories', [
            'id' => $category->id,
            'name' => 'Nuovo nome',
            'color' => '#ff0000',
        ]);
    }

    public function test_a_user_cannot_update_a_system_category()
    {
        $user = User::factory()->create();
        $category = TransactionCategory::factory()->create(['user_id' => null, 'name' => 'Alimentari']);

        $response = $this->actingAs($user)->patch(route('categories.update', $category), [
            'name' => 'Modificata',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseHas('transaction_categories', ['id' => $category->id, 'name' => 'Alimentari']);
    }

    public function test_a_user_cannot_update_another_users_category()
    {
        $user = User::factory()->create();
        $category = TransactionCategory::factory()->create(['user_id' => User::factory(), 'name' => 'Altrui']);

        $response = $this->actingAs($user)->patch(route('categories.update', $category), [
            'name' => 'Modificata',
        ]);

        $response->assertForbidden();
    }

    public function test_a_user_can_delete_their_own_category()
    {
        $user = User::factory()->create();
        $category = TransactionCategory::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->delete(route('categories.destroy', $category));

        $response->assertRedirect();
        $this->assertDatabaseMissing('transaction_categories', ['id' => $category->id]);
    }

    public function test_a_user_cannot_delete_a_system_category()
    {
        $user = User::factory()->create();
        $category = TransactionCategory::factory()->create(['user_id' => null]);

        $response = $this->actingAs($user)->delete(route('categories.destroy', $category));

        $response->assertForbidden();
        $this->assertDatabaseHas('transaction_categories', ['id' => $category->id]);
    }
}
