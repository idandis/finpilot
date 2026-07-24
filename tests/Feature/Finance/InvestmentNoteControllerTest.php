<?php

namespace Tests\Feature\Finance;

use App\Models\InvestmentNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvestmentNoteControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_add_a_note_to_a_position()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('investments.notes.store', 'IE00BK5BQT80'), [
            'body' => 'Comprato per il lungo termine',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('investment_notes', [
            'user_id' => $user->id,
            'isin' => 'IE00BK5BQT80',
            'body' => 'Comprato per il lungo termine',
        ]);
    }

    public function test_the_note_body_is_required()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('investments.notes.store', 'IE00BK5BQT80'), [
            'body' => '',
        ]);

        $response->assertSessionHasErrors('body');
        $this->assertDatabaseCount('investment_notes', 0);
    }

    public function test_a_user_can_delete_their_own_note()
    {
        $user = User::factory()->create();
        $note = InvestmentNote::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->delete(route('investments.notes.destroy', $note));

        $response->assertRedirect();
        $this->assertDatabaseMissing('investment_notes', ['id' => $note->id]);
    }

    public function test_a_user_cannot_delete_another_users_note()
    {
        $user = User::factory()->create();
        $note = InvestmentNote::factory()->create();

        $response = $this->actingAs($user)->delete(route('investments.notes.destroy', $note));

        $response->assertForbidden();
        $this->assertDatabaseHas('investment_notes', ['id' => $note->id]);
    }

    public function test_guests_cannot_add_or_delete_notes()
    {
        $note = InvestmentNote::factory()->create();

        $this->post(route('investments.notes.store', 'IE00BK5BQT80'), ['body' => 'x'])
            ->assertRedirect(route('login'));

        $this->delete(route('investments.notes.destroy', $note))
            ->assertRedirect(route('login'));
    }
}
