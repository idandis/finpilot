<?php

namespace Tests\Feature\Life;

use App\Models\Memory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MemoryControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_create_a_memory()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('memories.store'), [
            'memory_date' => '2026-03-03',
            'title' => 'Weekend in montagna',
            'description' => 'Bellissima gita',
            'location' => 'Dolomiti',
            'people' => 'Marco, Giulia',
            'mood' => 'ottimo',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('memories', [
            'user_id' => $user->id,
            'title' => 'Weekend in montagna',
            'memory_date' => '2026-03-03 00:00:00',
            'location' => 'Dolomiti',
            'mood' => 'ottimo',
        ]);
    }

    public function test_a_memory_can_be_created_with_only_a_title_and_date()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('memories.store'), [
            'memory_date' => '2026-03-03',
            'title' => 'Solo titolo',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('memories', ['title' => 'Solo titolo', 'mood' => null, 'description' => null]);
    }

    public function test_an_empty_mood_is_treated_as_none()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('memories.store'), [
            'memory_date' => '2026-03-03',
            'title' => 'Senza mood',
            'mood' => '',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('memories', ['title' => 'Senza mood', 'mood' => null]);
    }

    public function test_creating_a_memory_with_an_invalid_mood_fails_validation()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('memories.store'), [
            'memory_date' => '2026-03-03',
            'title' => 'Mood inventato',
            'mood' => 'non-esiste',
        ]);

        $response->assertSessionHasErrors('mood');
    }

    public function test_creating_a_memory_requires_a_title_and_date()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('memories.store'), [
            'memory_date' => '',
            'title' => '',
        ]);

        $response->assertSessionHasErrors(['title', 'memory_date']);
    }

    public function test_a_user_can_upload_a_photo_with_a_memory()
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('memories.store'), [
            'memory_date' => '2026-03-03',
            'title' => 'Con foto',
            'photo' => UploadedFile::fake()->image('foto.jpg'),
        ]);

        $response->assertRedirect();
        $memory = Memory::query()->where('title', 'Con foto')->firstOrFail();
        $this->assertNotNull($memory->photo_path);
        Storage::disk('public')->assertExists($memory->photo_path);
    }

    public function test_a_non_image_photo_fails_validation()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('memories.store'), [
            'memory_date' => '2026-03-03',
            'title' => 'File sbagliato',
            'photo' => UploadedFile::fake()->create('documento.pdf', 100),
        ]);

        $response->assertSessionHasErrors('photo');
    }

    public function test_a_user_can_delete_their_own_memory()
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $memory = Memory::factory()->create(['user_id' => $user->id, 'photo_path' => 'memories/foo.jpg']);
        Storage::disk('public')->put('memories/foo.jpg', 'fake-content');

        $response = $this->actingAs($user)->delete(route('memories.destroy', $memory));

        $response->assertRedirect();
        $this->assertDatabaseMissing('memories', ['id' => $memory->id]);
        Storage::disk('public')->assertMissing('memories/foo.jpg');
    }

    public function test_a_user_cannot_delete_another_users_memory()
    {
        $user = User::factory()->create();
        $memory = Memory::factory()->create(['user_id' => User::factory()]);

        $response = $this->actingAs($user)->delete(route('memories.destroy', $memory));

        $response->assertForbidden();
        $this->assertDatabaseHas('memories', ['id' => $memory->id]);
    }

    public function test_a_user_can_update_their_own_memory()
    {
        $user = User::factory()->create();
        $memory = Memory::factory()->create(['user_id' => $user->id, 'title' => 'Vecchio titolo', 'memory_date' => '2026-03-03']);

        $response = $this->actingAs($user)->patch(route('memories.update', $memory), [
            'memory_date' => '2026-03-03',
            'title' => 'Nuovo titolo',
            'mood' => 'buono',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('memories', ['id' => $memory->id, 'title' => 'Nuovo titolo', 'mood' => 'buono']);
    }

    public function test_updating_a_memory_with_a_new_photo_replaces_the_old_one()
    {
        Storage::fake('public');
        $user = User::factory()->create();
        Storage::disk('public')->put('memories/old.jpg', 'old-content');
        $memory = Memory::factory()->create(['user_id' => $user->id, 'memory_date' => '2026-03-03', 'photo_path' => 'memories/old.jpg']);

        $response = $this->actingAs($user)->patch(route('memories.update', $memory), [
            'memory_date' => '2026-03-03',
            'title' => $memory->title,
            'photo' => UploadedFile::fake()->image('new.jpg'),
        ]);

        $response->assertRedirect();
        $memory->refresh();
        $this->assertNotSame('memories/old.jpg', $memory->photo_path);
        Storage::disk('public')->assertMissing('memories/old.jpg');
        Storage::disk('public')->assertExists($memory->photo_path);
    }

    public function test_updating_a_memory_without_a_new_photo_keeps_the_existing_one()
    {
        Storage::fake('public');
        $user = User::factory()->create();
        Storage::disk('public')->put('memories/keep.jpg', 'content');
        $memory = Memory::factory()->create(['user_id' => $user->id, 'memory_date' => '2026-03-03', 'photo_path' => 'memories/keep.jpg']);

        $response = $this->actingAs($user)->patch(route('memories.update', $memory), [
            'memory_date' => '2026-03-03',
            'title' => 'Titolo aggiornato',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('memories', ['id' => $memory->id, 'photo_path' => 'memories/keep.jpg']);
        Storage::disk('public')->assertExists('memories/keep.jpg');
    }

    public function test_a_user_cannot_update_another_users_memory()
    {
        $user = User::factory()->create();
        $memory = Memory::factory()->create(['user_id' => User::factory(), 'title' => 'Non mio']);

        $response = $this->actingAs($user)->patch(route('memories.update', $memory), [
            'memory_date' => '2026-03-03',
            'title' => 'Rubato',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseHas('memories', ['id' => $memory->id, 'title' => 'Non mio']);
    }
}
