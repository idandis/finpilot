<?php

namespace App\Http\Controllers;

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Services\Ai\OpenAiChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class AiChatController extends Controller
{
    /**
     * The user's most recent conversation, if any - "Nuova chat" on the
     * frontend just clears the client-side state and drops the
     * conversation id, so the next message sent starts a fresh row (see
     * store()) rather than appending to this one.
     */
    public function index(Request $request): Response
    {
        $conversation = AiConversation::query()
            ->where('user_id', $request->user()->id)
            ->latest('id')
            ->first();

        return Inertia::render('AiChat/Index', [
            'conversationId' => $conversation?->id,
            'messages' => $conversation
                ? $conversation->messages()->orderBy('id')->get(['role', 'content'])
                : [],
        ]);
    }

    /**
     * Send a message and get the assistant's reply. Returns JSON rather than
     * an Inertia redirect, so the chat page can append the reply in place
     * without a full page visit - the same on-demand-fetch pattern
     * PasswordEntryController::reveal() uses for revealing a password.
     *
     * The conversation to append to is whichever id the client sends - null
     * (a brand-new chat, or "Nuova chat" was just clicked) creates a fresh
     * one. A non-null id must belong to the requesting user, the same
     * ownership check every other resource in the app applies.
     */
    public function store(Request $request, OpenAiChatService $chat): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:4000'],
            'conversation_id' => ['nullable', 'integer'],
        ]);

        $conversation = isset($validated['conversation_id'])
            ? AiConversation::query()->where('user_id', $request->user()->id)->findOrFail($validated['conversation_id'])
            : AiConversation::query()->create(['user_id' => $request->user()->id]);

        $conversation->messages()->create([
            'role' => 'user',
            'content' => $validated['message'],
        ]);

        $history = $conversation->messages()
            ->orderBy('id')
            ->get(['role', 'content'])
            ->map(fn (AiMessage $message) => ['role' => $message->role, 'content' => $message->content])
            ->all();

        try {
            $reply = $chat->reply($history, $request->user());
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'error' => 'Non sono riuscito a rispondere in questo momento. Riprova tra poco.',
            ], 502);
        }

        $assistantMessage = $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $reply,
        ]);

        return response()->json([
            'conversation_id' => $conversation->id,
            'message' => ['role' => 'assistant', 'content' => $assistantMessage->content],
        ]);
    }
}
