<?php

namespace Tests\Feature\Passwords;

use App\Models\PasswordEntry;
use App\Models\PasswordGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PasswordGroupControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('passwords.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_a_user_sees_only_their_own_groups()
    {
        $user = User::factory()->create();
        PasswordGroup::factory()->create(['user_id' => $user->id, 'name' => 'Lavoro']);
        PasswordGroup::factory()->create(['user_id' => $user->id, 'name' => 'Personale']);
        PasswordGroup::factory()->create(['user_id' => User::factory(), 'name' => 'Altrui']);

        $response = $this->actingAs($user)->get(route('passwords.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->has('groups', 2));
    }

    public function test_groups_are_ordered_alphabetically_with_their_entries()
    {
        $user = User::factory()->create();
        $work = PasswordGroup::factory()->create(['user_id' => $user->id, 'name' => 'Lavoro']);
        PasswordEntry::factory()->create(['password_group_id' => $work->id, 'platform_name' => 'GitHub', 'username' => 'iana']);

        $response = $this->actingAs($user)->get(route('passwords.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('groups.0.name', 'Lavoro')
            ->where('groups.0.entries.0.platform_name', 'GitHub')
            ->where('groups.0.entries.0.username', 'iana')
        );
    }

    public function test_the_index_payload_never_includes_the_password_column()
    {
        $user = User::factory()->create();
        $group = PasswordGroup::factory()->create(['user_id' => $user->id]);
        PasswordEntry::factory()->create(['password_group_id' => $group->id, 'password' => 'SuperSegreta123!']);

        $response = $this->actingAs($user)->get(route('passwords.index'));

        $response->assertOk();
        $response->assertInertia(function ($page) {
            $entry = $page->toArray()['props']['groups'][0]['entries'][0];
            $this->assertArrayNotHasKey('password', $entry);
        });
    }

    public function test_a_user_can_create_a_group()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('password-groups.store'), ['name' => 'Lavoro']);

        $response->assertRedirect();
        $this->assertDatabaseHas('password_groups', ['user_id' => $user->id, 'name' => 'Lavoro']);
    }

    public function test_creating_a_group_requires_a_name()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('password-groups.store'), ['name' => '']);

        $response->assertSessionHasErrors('name');
    }

    public function test_a_user_can_rename_their_own_group()
    {
        $user = User::factory()->create();
        $group = PasswordGroup::factory()->create(['user_id' => $user->id, 'name' => 'Vecchio']);

        $response = $this->actingAs($user)->patch(route('password-groups.update', $group), ['name' => 'Nuovo']);

        $response->assertRedirect();
        $this->assertDatabaseHas('password_groups', ['id' => $group->id, 'name' => 'Nuovo']);
    }

    public function test_a_user_cannot_rename_another_users_group()
    {
        $user = User::factory()->create();
        $group = PasswordGroup::factory()->create(['user_id' => User::factory(), 'name' => 'Altrui']);

        $response = $this->actingAs($user)->patch(route('password-groups.update', $group), ['name' => 'Rubato']);

        $response->assertForbidden();
        $this->assertDatabaseHas('password_groups', ['id' => $group->id, 'name' => 'Altrui']);
    }

    public function test_a_user_can_delete_their_own_group_and_its_entries_cascade()
    {
        $user = User::factory()->create();
        $group = PasswordGroup::factory()->create(['user_id' => $user->id]);
        $entry = PasswordEntry::factory()->create(['password_group_id' => $group->id]);

        $response = $this->actingAs($user)->delete(route('password-groups.destroy', $group));

        $response->assertRedirect();
        $this->assertDatabaseMissing('password_groups', ['id' => $group->id]);
        $this->assertDatabaseMissing('password_entries', ['id' => $entry->id]);
    }

    public function test_a_user_cannot_delete_another_users_group()
    {
        $user = User::factory()->create();
        $group = PasswordGroup::factory()->create(['user_id' => User::factory()]);

        $response = $this->actingAs($user)->delete(route('password-groups.destroy', $group));

        $response->assertForbidden();
        $this->assertDatabaseHas('password_groups', ['id' => $group->id]);
    }
}
