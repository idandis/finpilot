<?php

namespace Tests\Feature\Shopping;

use App\Models\ShoppingList;
use App\Models\ShoppingListItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShoppingListItemControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_add_a_product_to_their_own_list()
    {
        $user = User::factory()->create();
        $list = ShoppingList::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post(route('shopping-list-items.store', $list), [
            'name' => 'Mele',
            'category' => 'frutta',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('shopping_list_items', [
            'shopping_list_id' => $list->id,
            'name' => 'Mele',
            'category' => 'frutta',
            'position' => 0,
        ]);
    }

    public function test_new_products_are_appended_to_the_end_of_their_category()
    {
        $user = User::factory()->create();
        $list = ShoppingList::factory()->create(['user_id' => $user->id]);
        ShoppingListItem::factory()->create(['shopping_list_id' => $list->id, 'category' => 'frutta', 'position' => 0]);
        ShoppingListItem::factory()->create(['shopping_list_id' => $list->id, 'category' => 'frutta', 'position' => 1]);
        ShoppingListItem::factory()->create(['shopping_list_id' => $list->id, 'category' => 'verdura', 'position' => 0]);

        $this->actingAs($user)->post(route('shopping-list-items.store', $list), [
            'name' => 'Pere',
            'category' => 'frutta',
        ]);

        $this->assertDatabaseHas('shopping_list_items', ['name' => 'Pere', 'category' => 'frutta', 'position' => 2]);
    }

    public function test_a_user_cannot_add_a_product_to_another_users_list()
    {
        $user = User::factory()->create();
        $list = ShoppingList::factory()->create(['user_id' => User::factory()]);

        $response = $this->actingAs($user)->post(route('shopping-list-items.store', $list), [
            'name' => 'Mele',
            'category' => 'frutta',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('shopping_list_items', ['shopping_list_id' => $list->id]);
    }

    public function test_adding_a_product_requires_a_name_and_a_valid_category()
    {
        $user = User::factory()->create();
        $list = ShoppingList::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post(route('shopping-list-items.store', $list), [
            'name' => '',
            'category' => 'non-existent',
        ]);

        $response->assertSessionHasErrors(['name', 'category']);
    }

    public function test_a_user_can_move_their_own_product_to_another_category()
    {
        $user = User::factory()->create();
        $list = ShoppingList::factory()->create(['user_id' => $user->id]);
        $item = ShoppingListItem::factory()->create(['shopping_list_id' => $list->id, 'category' => 'frutta']);

        $response = $this->actingAs($user)->patch(route('shopping-list-items.move', $item), ['category' => 'verdura']);

        $response->assertRedirect();
        $this->assertDatabaseHas('shopping_list_items', ['id' => $item->id, 'category' => 'verdura']);
    }

    public function test_moving_a_product_appends_it_to_the_end_of_the_target_category()
    {
        $user = User::factory()->create();
        $list = ShoppingList::factory()->create(['user_id' => $user->id]);
        ShoppingListItem::factory()->create(['shopping_list_id' => $list->id, 'category' => 'verdura', 'position' => 0]);
        ShoppingListItem::factory()->create(['shopping_list_id' => $list->id, 'category' => 'verdura', 'position' => 1]);
        $item = ShoppingListItem::factory()->create(['shopping_list_id' => $list->id, 'category' => 'frutta', 'position' => 0]);

        $this->actingAs($user)->patch(route('shopping-list-items.move', $item), ['category' => 'verdura']);

        $this->assertDatabaseHas('shopping_list_items', ['id' => $item->id, 'category' => 'verdura', 'position' => 2]);
    }

    public function test_moving_requires_a_valid_category()
    {
        $user = User::factory()->create();
        $list = ShoppingList::factory()->create(['user_id' => $user->id]);
        $item = ShoppingListItem::factory()->create(['shopping_list_id' => $list->id]);

        $response = $this->actingAs($user)->patch(route('shopping-list-items.move', $item), ['category' => 'non-existent']);

        $response->assertSessionHasErrors('category');
    }

    public function test_a_user_cannot_move_another_users_product()
    {
        $user = User::factory()->create();
        $list = ShoppingList::factory()->create(['user_id' => User::factory()]);
        $item = ShoppingListItem::factory()->create(['shopping_list_id' => $list->id, 'category' => 'frutta']);

        $response = $this->actingAs($user)->patch(route('shopping-list-items.move', $item), ['category' => 'verdura']);

        $response->assertForbidden();
        $this->assertDatabaseHas('shopping_list_items', ['id' => $item->id, 'category' => 'frutta']);
    }

    public function test_a_user_can_delete_their_own_product()
    {
        $user = User::factory()->create();
        $list = ShoppingList::factory()->create(['user_id' => $user->id]);
        $item = ShoppingListItem::factory()->create(['shopping_list_id' => $list->id]);

        $response = $this->actingAs($user)->delete(route('shopping-list-items.destroy', $item));

        $response->assertRedirect();
        $this->assertDatabaseMissing('shopping_list_items', ['id' => $item->id]);
    }

    public function test_a_user_cannot_delete_another_users_product()
    {
        $user = User::factory()->create();
        $list = ShoppingList::factory()->create(['user_id' => User::factory()]);
        $item = ShoppingListItem::factory()->create(['shopping_list_id' => $list->id]);

        $response = $this->actingAs($user)->delete(route('shopping-list-items.destroy', $item));

        $response->assertForbidden();
        $this->assertDatabaseHas('shopping_list_items', ['id' => $item->id]);
    }

    public function test_a_user_can_mark_their_own_product_as_purchased()
    {
        $user = User::factory()->create();
        $list = ShoppingList::factory()->create(['user_id' => $user->id]);
        $item = ShoppingListItem::factory()->create(['shopping_list_id' => $list->id, 'purchased' => false]);

        $response = $this->actingAs($user)->patch(route('shopping-list-items.toggle', $item));

        $response->assertRedirect();
        $this->assertDatabaseHas('shopping_list_items', ['id' => $item->id, 'purchased' => true]);
    }

    public function test_toggling_twice_unmarks_a_product_as_purchased()
    {
        $user = User::factory()->create();
        $list = ShoppingList::factory()->create(['user_id' => $user->id]);
        $item = ShoppingListItem::factory()->create(['shopping_list_id' => $list->id, 'purchased' => true]);

        $this->actingAs($user)->patch(route('shopping-list-items.toggle', $item));

        $this->assertDatabaseHas('shopping_list_items', ['id' => $item->id, 'purchased' => false]);
    }

    public function test_a_user_cannot_toggle_another_users_product()
    {
        $user = User::factory()->create();
        $list = ShoppingList::factory()->create(['user_id' => User::factory()]);
        $item = ShoppingListItem::factory()->create(['shopping_list_id' => $list->id, 'purchased' => false]);

        $response = $this->actingAs($user)->patch(route('shopping-list-items.toggle', $item));

        $response->assertForbidden();
        $this->assertDatabaseHas('shopping_list_items', ['id' => $item->id, 'purchased' => false]);
    }
}
