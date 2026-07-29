<?php

namespace Tests\Feature\Calendar;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_creates_a_timed_event()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('events.store'), [
            'title' => 'Riunione con il team',
            'type' => 'evento',
            'start_at' => '2026-08-03 10:00:00',
            'end_at' => '2026-08-03 11:00:00',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('events', [
            'user_id' => $user->id,
            'title' => 'Riunione con il team',
            'type' => 'evento',
        ]);
    }

    public function test_store_requires_end_at_for_a_timed_event()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('events.store'), [
            'title' => 'Senza fine',
            'type' => 'evento',
            'start_at' => '2026-08-03 10:00:00',
        ]);

        $response->assertSessionHasErrors('end_at');
        $this->assertDatabaseMissing('events', ['title' => 'Senza fine']);
    }

    public function test_store_allows_an_all_day_event_without_end_at()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('events.store'), [
            'title' => 'Ferie',
            'type' => 'evento',
            'all_day' => true,
            'start_at' => '2026-08-03',
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('events', ['title' => 'Ferie', 'all_day' => true, 'end_at' => null]);
    }

    public function test_store_normalizes_a_promemoria_to_have_no_end_at()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('events.store'), [
            'title' => 'Chiamare il commercialista',
            'type' => 'promemoria',
            'start_at' => '2026-08-03 09:00:00',
            'end_at' => '2026-08-03 18:00:00',
            'all_day' => true,
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('events', [
            'title' => 'Chiamare il commercialista',
            'type' => 'promemoria',
            'end_at' => null,
            'all_day' => false,
        ]);
    }

    public function test_store_requires_a_title()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('events.store'), [
            'type' => 'promemoria',
            'start_at' => '2026-08-03 09:00:00',
        ]);

        $response->assertSessionHasErrors('title');
    }

    public function test_update_modifies_an_owned_event()
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $user->id, 'title' => 'Vecchio titolo']);

        $response = $this->actingAs($user)->patch(route('events.update', $event), [
            'title' => 'Nuovo titolo',
            'type' => 'evento',
            'start_at' => $event->start_at->toDateTimeString(),
            'end_at' => $event->start_at->copy()->addHour()->toDateTimeString(),
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('events', ['id' => $event->id, 'title' => 'Nuovo titolo']);
    }

    public function test_update_is_forbidden_for_another_users_event()
    {
        $user = User::factory()->create();
        $event = Event::factory()->create();

        $response = $this->actingAs($user)->patch(route('events.update', $event), [
            'title' => 'Tentativo',
            'type' => 'evento',
            'start_at' => $event->start_at->toDateTimeString(),
            'end_at' => $event->start_at->copy()->addHour()->toDateTimeString(),
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('events', ['id' => $event->id, 'title' => 'Tentativo']);
    }

    public function test_destroy_deletes_an_owned_event()
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->delete(route('events.destroy', $event));

        $response->assertRedirect();
        $this->assertDatabaseMissing('events', ['id' => $event->id]);
    }

    public function test_destroy_is_forbidden_for_another_users_event()
    {
        $user = User::factory()->create();
        $event = Event::factory()->create();

        $response = $this->actingAs($user)->delete(route('events.destroy', $event));

        $response->assertForbidden();
        $this->assertDatabaseHas('events', ['id' => $event->id]);
    }

    public function test_reschedule_moves_the_event_preserving_its_duration()
    {
        $user = User::factory()->create();
        $event = Event::factory()->create([
            'user_id' => $user->id,
            'start_at' => '2026-08-03 10:00:00',
            'end_at' => '2026-08-03 11:30:00',
        ]);

        $response = $this->actingAs($user)->patch(route('events.reschedule', $event), [
            'start_at' => '2026-08-04 14:00:00',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'start_at' => '2026-08-04 14:00:00',
            'end_at' => '2026-08-04 15:30:00',
        ]);
    }

    public function test_reschedule_keeps_a_promemoria_without_an_end_at()
    {
        $user = User::factory()->create();
        $event = Event::factory()->promemoria()->create([
            'user_id' => $user->id,
            'start_at' => '2026-08-03 10:00:00',
        ]);

        $response = $this->actingAs($user)->patch(route('events.reschedule', $event), [
            'start_at' => '2026-08-03 16:00:00',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('events', ['id' => $event->id, 'start_at' => '2026-08-03 16:00:00', 'end_at' => null]);
    }

    public function test_reschedule_is_forbidden_for_another_users_event()
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['start_at' => '2026-08-03 10:00:00', 'end_at' => '2026-08-03 11:00:00']);

        $response = $this->actingAs($user)->patch(route('events.reschedule', $event), [
            'start_at' => '2026-08-03 16:00:00',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseHas('events', ['id' => $event->id, 'start_at' => '2026-08-03 10:00:00']);
    }

    public function test_resize_changes_the_end_time()
    {
        $user = User::factory()->create();
        $event = Event::factory()->create([
            'user_id' => $user->id,
            'start_at' => '2026-08-03 10:00:00',
            'end_at' => '2026-08-03 11:00:00',
        ]);

        $response = $this->actingAs($user)->patch(route('events.resize', $event), [
            'end_at' => '2026-08-03 12:30:00',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('events', ['id' => $event->id, 'end_at' => '2026-08-03 12:30:00']);
    }

    public function test_resize_rejects_an_end_before_the_start()
    {
        $user = User::factory()->create();
        $event = Event::factory()->create([
            'user_id' => $user->id,
            'start_at' => '2026-08-03 10:00:00',
            'end_at' => '2026-08-03 11:00:00',
        ]);

        $response = $this->actingAs($user)->patch(route('events.resize', $event), [
            'end_at' => '2026-08-03 09:00:00',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseHas('events', ['id' => $event->id, 'end_at' => '2026-08-03 11:00:00']);
    }

    public function test_resize_rejects_an_all_day_event()
    {
        $user = User::factory()->create();
        $event = Event::factory()->create([
            'user_id' => $user->id,
            'all_day' => true,
            'start_at' => '2026-08-03 00:00:00',
            'end_at' => null,
        ]);

        $response = $this->actingAs($user)->patch(route('events.resize', $event), [
            'end_at' => '2026-08-04 00:00:00',
        ]);

        $response->assertStatus(422);
    }

    public function test_resize_rejects_a_promemoria()
    {
        $user = User::factory()->create();
        $event = Event::factory()->promemoria()->create([
            'user_id' => $user->id,
            'start_at' => '2026-08-03 10:00:00',
        ]);

        $response = $this->actingAs($user)->patch(route('events.resize', $event), [
            'end_at' => '2026-08-03 11:00:00',
        ]);

        $response->assertStatus(422);
    }

    public function test_resize_is_forbidden_for_another_users_event()
    {
        $user = User::factory()->create();
        $event = Event::factory()->create([
            'start_at' => '2026-08-03 10:00:00',
            'end_at' => '2026-08-03 11:00:00',
        ]);

        $response = $this->actingAs($user)->patch(route('events.resize', $event), [
            'end_at' => '2026-08-03 12:00:00',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseHas('events', ['id' => $event->id, 'end_at' => '2026-08-03 11:00:00']);
    }
}
