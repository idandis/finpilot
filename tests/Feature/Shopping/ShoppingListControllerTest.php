<?php

namespace Tests\Feature\Shopping;

use App\Models\ShoppingList;
use App\Models\ShoppingListItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShoppingListControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('shopping-lists.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_a_user_sees_only_their_own_lists()
    {
        $user = User::factory()->create();
        ShoppingList::factory()->create(['user_id' => $user->id, 'name' => 'Settimanale']);
        ShoppingList::factory()->create(['user_id' => $user->id, 'name' => 'Cena']);
        ShoppingList::factory()->create(['user_id' => User::factory(), 'name' => 'Altrui']);

        $response = $this->actingAs($user)->get(route('shopping-lists.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->has('lists', 2));
    }

    public function test_a_user_can_view_their_own_list()
    {
        $user = User::factory()->create();
        $list = ShoppingList::factory()->create(['user_id' => $user->id, 'name' => 'Settimanale']);
        ShoppingListItem::factory()->create(['shopping_list_id' => $list->id, 'name' => 'Mele', 'category' => 'frutta']);

        $response = $this->actingAs($user)->get(route('shopping-lists.show', $list));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('list.name', 'Settimanale')
            ->where('list.items.0.name', 'Mele')
        );
    }

    public function test_a_user_cannot_view_another_users_list()
    {
        $user = User::factory()->create();
        $list = ShoppingList::factory()->create(['user_id' => User::factory()]);

        $response = $this->actingAs($user)->get(route('shopping-lists.show', $list));

        $response->assertForbidden();
    }

    public function test_index_exposes_the_fixed_grocery_categories()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('shopping-lists.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('categories.frutta', 'Frutta')
            ->where('categories.pesce', 'Pesce')
        );
    }

    public function test_a_list_includes_its_items_ordered_by_position()
    {
        $user = User::factory()->create();
        $list = ShoppingList::factory()->create(['user_id' => $user->id]);
        ShoppingListItem::factory()->create(['shopping_list_id' => $list->id, 'name' => 'Mele', 'category' => 'frutta', 'position' => 1]);
        ShoppingListItem::factory()->create(['shopping_list_id' => $list->id, 'name' => 'Banane', 'category' => 'frutta', 'position' => 0]);

        $response = $this->actingAs($user)->get(route('shopping-lists.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('lists.0.items.0.name', 'Banane')
            ->where('lists.0.items.1.name', 'Mele')
        );
    }

    public function test_a_user_can_create_a_list()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('shopping-lists.store'), ['name' => 'Spesa settimanale']);

        $response->assertRedirect();
        $this->assertDatabaseHas('shopping_lists', ['user_id' => $user->id, 'name' => 'Spesa settimanale']);
    }

    public function test_creating_a_list_requires_a_name()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('shopping-lists.store'), ['name' => '']);

        $response->assertSessionHasErrors('name');
    }

    public function test_a_user_can_rename_their_own_list()
    {
        $user = User::factory()->create();
        $list = ShoppingList::factory()->create(['user_id' => $user->id, 'name' => 'Vecchio']);

        $response = $this->actingAs($user)->patch(route('shopping-lists.update', $list), ['name' => 'Nuovo']);

        $response->assertRedirect();
        $this->assertDatabaseHas('shopping_lists', ['id' => $list->id, 'name' => 'Nuovo']);
    }

    public function test_a_user_cannot_rename_another_users_list()
    {
        $user = User::factory()->create();
        $list = ShoppingList::factory()->create(['user_id' => User::factory(), 'name' => 'Altrui']);

        $response = $this->actingAs($user)->patch(route('shopping-lists.update', $list), ['name' => 'Rubato']);

        $response->assertForbidden();
        $this->assertDatabaseHas('shopping_lists', ['id' => $list->id, 'name' => 'Altrui']);
    }

    public function test_a_user_can_delete_their_own_list_and_its_items_cascade()
    {
        $user = User::factory()->create();
        $list = ShoppingList::factory()->create(['user_id' => $user->id]);
        $item = ShoppingListItem::factory()->create(['shopping_list_id' => $list->id]);

        $response = $this->actingAs($user)->delete(route('shopping-lists.destroy', $list));

        $response->assertRedirect();
        $this->assertDatabaseMissing('shopping_lists', ['id' => $list->id]);
        $this->assertDatabaseMissing('shopping_list_items', ['id' => $item->id]);
    }

    public function test_a_user_cannot_delete_another_users_list()
    {
        $user = User::factory()->create();
        $list = ShoppingList::factory()->create(['user_id' => User::factory()]);

        $response = $this->actingAs($user)->delete(route('shopping-lists.destroy', $list));

        $response->assertForbidden();
        $this->assertDatabaseHas('shopping_lists', ['id' => $list->id]);
    }
}
