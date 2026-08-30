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

    public function test_a_list_can_be_shared_with_another_user_by_email_and_shows_up_among_theirs()
    {
        $owner = User::factory()->create();
        $mate = User::factory()->create(['email' => 'mate@example.com']);
        $list = ShoppingList::factory()->create(['user_id' => $owner->id, 'name' => 'Settimanale']);

        $this->actingAs($owner)->post(route('shopping-lists.members.store', $list), ['email' => 'mate@example.com']);

        $this->assertTrue($list->members()->whereKey($mate->id)->exists());

        $this->actingAs($mate)->get(route('shopping-lists.index'))
            ->assertInertia(fn ($page) => $page
                ->has('lists', 1)
                ->where('lists.0.name', 'Settimanale')
                ->where('lists.0.is_shared', true)
            );
    }

    public function test_the_grid_carries_the_people_each_list_is_shared_with()
    {
        $owner = User::factory()->create(['name' => 'Iana Longo']);
        $mate = User::factory()->create(['name' => 'Nicolas Picco']);
        $shared = ShoppingList::factory()->create(['user_id' => $owner->id, 'name' => 'Condivisa']);
        ShoppingList::factory()->create(['user_id' => $owner->id, 'name' => 'Solo mia']);
        $shared->members()->attach($mate->id);

        $response = $this->actingAs($owner)->get(route('shopping-lists.index'));

        $response->assertInertia(fn ($page) => $page
            ->has('lists', 2)
            ->where('lists.0.name', 'Condivisa')
            ->where('lists.0.is_owner', true)
            ->has('lists.0.people', 2)
            ->where('lists.0.people.0.name', 'Iana Longo')
            ->where('lists.0.people.1.name', 'Nicolas Picco')
            ->has('lists.1.people', 1)
        );
    }

    public function test_a_member_opens_the_shared_list_and_sees_everyone_on_it()
    {
        $owner = User::factory()->create(['name' => 'Iana Longo']);
        $mate = User::factory()->create();
        $list = ShoppingList::factory()->create(['user_id' => $owner->id]);
        $list->members()->attach($mate->id);

        $response = $this->actingAs($mate)->get(route('shopping-lists.show', $list));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('list.is_owner', false)
            ->has('list.people', 2)
            ->where('list.people.0.name', 'Iana Longo')
            ->where('list.people.0.is_owner', true)
        );
    }

    public function test_an_unknown_email_cannot_be_invited()
    {
        $owner = User::factory()->create();
        $list = ShoppingList::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($owner)->post(route('shopping-lists.members.store', $list), ['email' => 'nessuno@example.com']);

        $response->assertSessionHasErrors('email');
        $this->assertSame(0, $list->members()->count());
    }

    public function test_the_owner_cannot_invite_themselves_or_an_existing_member_twice()
    {
        $owner = User::factory()->create(['email' => 'owner@example.com']);
        $mate = User::factory()->create(['email' => 'mate@example.com']);
        $list = ShoppingList::factory()->create(['user_id' => $owner->id]);
        $list->members()->attach($mate->id);

        $this->actingAs($owner)
            ->post(route('shopping-lists.members.store', $list), ['email' => 'owner@example.com'])
            ->assertSessionHasErrors('email');

        $this->actingAs($owner)
            ->post(route('shopping-lists.members.store', $list), ['email' => 'mate@example.com'])
            ->assertSessionHasErrors('email');

        $this->assertSame(1, $list->members()->count());
    }

    public function test_only_the_owner_can_invite_rename_or_delete_a_shared_list()
    {
        $owner = User::factory()->create();
        $mate = User::factory()->create();
        $stranger = User::factory()->create();
        $list = ShoppingList::factory()->create(['user_id' => $owner->id, 'name' => 'Settimanale']);
        $list->members()->attach($mate->id);

        $this->actingAs($mate)
            ->post(route('shopping-lists.members.store', $list), ['email' => $stranger->email])
            ->assertForbidden();
        $this->actingAs($mate)
            ->patch(route('shopping-lists.update', $list), ['name' => 'Mia'])
            ->assertForbidden();
        $this->actingAs($mate)
            ->delete(route('shopping-lists.destroy', $list))
            ->assertForbidden();

        $this->assertSame('Settimanale', $list->fresh()->name);
        $this->assertDatabaseHas('shopping_lists', ['id' => $list->id]);
    }

    public function test_a_member_can_leave_a_list_but_cannot_remove_another_member()
    {
        $owner = User::factory()->create();
        $mate = User::factory()->create();
        $other = User::factory()->create();
        $list = ShoppingList::factory()->create(['user_id' => $owner->id]);
        $list->members()->attach([$mate->id, $other->id]);

        $this->actingAs($mate)
            ->delete(route('shopping-lists.members.destroy', [$list, $other]))
            ->assertForbidden();

        $response = $this->actingAs($mate)->delete(route('shopping-lists.members.destroy', [$list, $mate]));

        $response->assertRedirect(route('shopping-lists.index'));
        $this->assertFalse($list->members()->whereKey($mate->id)->exists());
        $this->assertTrue($list->members()->whereKey($other->id)->exists());
    }

    public function test_the_owner_can_remove_a_member_and_cannot_be_removed_themselves()
    {
        $owner = User::factory()->create();
        $mate = User::factory()->create();
        $list = ShoppingList::factory()->create(['user_id' => $owner->id]);
        $list->members()->attach($mate->id);

        $this->actingAs($owner)->delete(route('shopping-lists.members.destroy', [$list, $mate]));
        $this->assertSame(0, $list->members()->count());

        $this->actingAs($owner)
            ->delete(route('shopping-lists.members.destroy', [$list, $owner]))
            ->assertForbidden();
    }

    public function test_a_list_that_is_not_shared_with_the_user_stays_out_of_reach()
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $list = ShoppingList::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($stranger)->get(route('shopping-lists.show', $list))->assertForbidden();
        $this->actingAs($stranger)->get(route('shopping-lists.index'))
            ->assertInertia(fn ($page) => $page->has('lists', 0));
    }
}
