<?php

namespace Tests\Feature\Passwords;

use App\Models\PasswordEntry;
use App\Models\PasswordGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PasswordEntryControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_add_an_entry_to_their_own_group()
    {
        $user = User::factory()->create();
        $group = PasswordGroup::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post(route('password-entries.store', $group), [
            'platform_name' => 'GitHub',
            'username' => 'iana',
            'password' => 'SuperSegreta123!',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('password_entries', [
            'password_group_id' => $group->id,
            'platform_name' => 'GitHub',
            'username' => 'iana',
        ]);
        $entry = PasswordEntry::query()->where('password_group_id', $group->id)->firstOrFail();
        $this->assertSame('SuperSegreta123!', $entry->password);
    }

    public function test_the_password_is_stored_encrypted_at_rest()
    {
        $user = User::factory()->create();
        $group = PasswordGroup::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)->post(route('password-entries.store', $group), [
            'platform_name' => 'GitHub',
            'username' => 'iana',
            'password' => 'SuperSegreta123!',
        ]);

        $rawValue = DB::table('password_entries')->where('password_group_id', $group->id)->value('password');

        $this->assertStringNotContainsString('SuperSegreta123!', $rawValue);
    }

    public function test_a_user_cannot_add_an_entry_to_another_users_group()
    {
        $user = User::factory()->create();
        $group = PasswordGroup::factory()->create(['user_id' => User::factory()]);

        $response = $this->actingAs($user)->post(route('password-entries.store', $group), [
            'platform_name' => 'GitHub',
            'username' => 'iana',
            'password' => 'SuperSegreta123!',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('password_entries', ['password_group_id' => $group->id]);
    }

    public function test_adding_an_entry_requires_a_platform_name_and_password()
    {
        $user = User::factory()->create();
        $group = PasswordGroup::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post(route('password-entries.store', $group), [
            'platform_name' => '',
            'password' => '',
        ]);

        $response->assertSessionHasErrors(['platform_name', 'password']);
    }

    public function test_a_user_can_update_their_own_entry_without_changing_the_password()
    {
        $user = User::factory()->create();
        $group = PasswordGroup::factory()->create(['user_id' => $user->id]);
        $entry = PasswordEntry::factory()->create([
            'password_group_id' => $group->id,
            'platform_name' => 'Vecchio nome',
            'password' => 'SuperSegreta123!',
        ]);

        $response = $this->actingAs($user)->patch(route('password-entries.update', $entry), [
            'platform_name' => 'Nuovo nome',
            'username' => 'iana',
        ]);

        $response->assertRedirect();
        $entry->refresh();
        $this->assertSame('Nuovo nome', $entry->platform_name);
        $this->assertSame('SuperSegreta123!', $entry->password);
    }

    public function test_a_user_can_change_the_password_of_their_own_entry()
    {
        $user = User::factory()->create();
        $group = PasswordGroup::factory()->create(['user_id' => $user->id]);
        $entry = PasswordEntry::factory()->create(['password_group_id' => $group->id, 'password' => 'Vecchia123!']);

        $response = $this->actingAs($user)->patch(route('password-entries.update', $entry), [
            'platform_name' => $entry->platform_name,
            'password' => 'Nuova456!',
        ]);

        $response->assertRedirect();
        $this->assertSame('Nuova456!', $entry->fresh()->password);
    }

    public function test_a_user_cannot_update_another_users_entry()
    {
        $user = User::factory()->create();
        $group = PasswordGroup::factory()->create(['user_id' => User::factory()]);
        $entry = PasswordEntry::factory()->create(['password_group_id' => $group->id, 'platform_name' => 'Originale']);

        $response = $this->actingAs($user)->patch(route('password-entries.update', $entry), [
            'platform_name' => 'Rubato',
        ]);

        $response->assertForbidden();
        $this->assertSame('Originale', $entry->fresh()->platform_name);
    }

    public function test_a_user_can_delete_their_own_entry()
    {
        $user = User::factory()->create();
        $group = PasswordGroup::factory()->create(['user_id' => $user->id]);
        $entry = PasswordEntry::factory()->create(['password_group_id' => $group->id]);

        $response = $this->actingAs($user)->delete(route('password-entries.destroy', $entry));

        $response->assertRedirect();
        $this->assertDatabaseMissing('password_entries', ['id' => $entry->id]);
    }

    public function test_a_user_cannot_delete_another_users_entry()
    {
        $user = User::factory()->create();
        $group = PasswordGroup::factory()->create(['user_id' => User::factory()]);
        $entry = PasswordEntry::factory()->create(['password_group_id' => $group->id]);

        $response = $this->actingAs($user)->delete(route('password-entries.destroy', $entry));

        $response->assertForbidden();
        $this->assertDatabaseHas('password_entries', ['id' => $entry->id]);
    }

    public function test_a_user_can_reveal_their_own_entrys_password()
    {
        $user = User::factory()->create();
        $group = PasswordGroup::factory()->create(['user_id' => $user->id]);
        $entry = PasswordEntry::factory()->create(['password_group_id' => $group->id, 'password' => 'SuperSegreta123!']);

        $response = $this->actingAs($user)->get(route('password-entries.reveal', $entry));

        $response->assertOk();
        $response->assertJson(['password' => 'SuperSegreta123!']);
    }

    public function test_a_user_cannot_reveal_another_users_entrys_password()
    {
        $user = User::factory()->create();
        $group = PasswordGroup::factory()->create(['user_id' => User::factory()]);
        $entry = PasswordEntry::factory()->create(['password_group_id' => $group->id]);

        $response = $this->actingAs($user)->get(route('password-entries.reveal', $entry));

        $response->assertForbidden();
    }

    public function test_guests_cannot_reveal_a_password()
    {
        $group = PasswordGroup::factory()->create();
        $entry = PasswordEntry::factory()->create(['password_group_id' => $group->id]);

        $response = $this->get(route('password-entries.reveal', $entry));

        $response->assertRedirect(route('login'));
    }
}
