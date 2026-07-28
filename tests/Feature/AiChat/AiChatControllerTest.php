<?php

namespace Tests\Feature\AiChat;

use App\Models\AiConversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiChatControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('ai-chat.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_a_new_user_sees_an_empty_chat()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('ai-chat.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->has('messages', 0));
    }

    public function test_prior_messages_are_shown_on_the_chat_page()
    {
        $user = User::factory()->create();
        $conversation = AiConversation::factory()->create(['user_id' => $user->id]);
        $conversation->messages()->create(['role' => 'user', 'content' => 'Ciao']);
        $conversation->messages()->create(['role' => 'assistant', 'content' => 'Ciao, come posso aiutarti?']);

        $response = $this->actingAs($user)->get(route('ai-chat.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('conversationId', $conversation->id)
            ->has('messages', 2)
            ->where('messages.0.content', 'Ciao')
            ->where('messages.1.content', 'Ciao, come posso aiutarti?')
        );
    }

    public function test_the_page_shows_the_most_recent_conversation()
    {
        $user = User::factory()->create();
        AiConversation::factory()->create(['user_id' => $user->id]);
        $latest = AiConversation::factory()->create(['user_id' => $user->id]);
        $latest->messages()->create(['role' => 'user', 'content' => 'Ultima chat']);

        $response = $this->actingAs($user)->get(route('ai-chat.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('conversationId', $latest->id)
            ->has('messages', 1)
        );
    }

    public function test_sending_a_message_saves_it_and_returns_the_assistant_reply()
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    ['message' => ['role' => 'assistant', 'content' => 'Il tuo saldo è di 100€.']],
                ],
            ]),
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('ai-chat.store'), [
            'message' => 'Quanto ho sul conto?',
        ]);

        $response->assertOk();
        $response->assertJson(['message' => ['role' => 'assistant', 'content' => 'Il tuo saldo è di 100€.']]);

        $this->assertDatabaseHas('ai_messages', ['role' => 'user', 'content' => 'Quanto ho sul conto?']);
        $this->assertDatabaseHas('ai_messages', ['role' => 'assistant', 'content' => 'Il tuo saldo è di 100€.']);
    }

    public function test_a_tool_call_is_resolved_before_the_final_reply_is_returned()
    {
        Http::fakeSequence('api.openai.com/*')
            ->push([
                'choices' => [
                    ['message' => [
                        'role' => 'assistant',
                        'content' => null,
                        'tool_calls' => [[
                            'id' => 'call_1',
                            'type' => 'function',
                            'function' => ['name' => 'saldo_conto', 'arguments' => '{}'],
                        ]],
                    ]],
                ],
            ])
            ->push([
                'choices' => [
                    ['message' => ['role' => 'assistant', 'content' => 'Il tuo saldo attuale è 0€.']],
                ],
            ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('ai-chat.store'), [
            'message' => 'Quanto ho sul conto?',
        ]);

        $response->assertOk();
        $response->assertJson(['message' => ['role' => 'assistant', 'content' => 'Il tuo saldo attuale è 0€.']]);
    }

    public function test_a_new_chat_is_a_separate_conversation_from_the_previous_one()
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    ['message' => ['role' => 'assistant', 'content' => 'Risposta.']],
                ],
            ]),
        ]);

        $user = User::factory()->create();
        $previous = AiConversation::factory()->create(['user_id' => $user->id]);
        $previous->messages()->create(['role' => 'user', 'content' => 'Vecchia domanda']);

        $response = $this->actingAs($user)->postJson(route('ai-chat.store'), [
            'message' => 'Nuova domanda',
            'conversation_id' => null,
        ]);

        $response->assertOk();
        $newConversationId = $response->json('conversation_id');

        $this->assertNotSame($previous->id, $newConversationId);
        $this->assertDatabaseHas('ai_messages', ['ai_conversation_id' => $newConversationId, 'content' => 'Nuova domanda']);
        $this->assertDatabaseMissing('ai_messages', ['ai_conversation_id' => $previous->id, 'content' => 'Nuova domanda']);
    }

    public function test_a_user_cannot_post_to_another_users_conversation()
    {
        $user = User::factory()->create();
        $otherUsersConversation = AiConversation::factory()->create(['user_id' => User::factory()]);

        $response = $this->actingAs($user)->postJson(route('ai-chat.store'), [
            'message' => 'Ciao',
            'conversation_id' => $otherUsersConversation->id,
        ]);

        $response->assertNotFound();
    }

    public function test_a_message_is_required()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('ai-chat.store'), ['message' => '']);

        $response->assertJsonValidationErrors('message');
    }

    public function test_an_openai_failure_returns_a_friendly_error_without_leaking_details()
    {
        Http::fake(['api.openai.com/*' => Http::response('server error', 500)]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('ai-chat.store'), [
            'message' => 'Quanto ho sul conto?',
        ]);

        $response->assertStatus(502);
        $response->assertJson(['error' => 'Non sono riuscito a rispondere in questo momento. Riprova tra poco.']);
    }
}
