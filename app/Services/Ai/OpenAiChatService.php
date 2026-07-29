<?php

namespace App\Services\Ai;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Talks to OpenAI's Chat Completions API and drives the function-calling
 * loop: send the conversation, and whenever the model asks for a tool,
 * run it through AiToolExecutor (scoped to the given user) and feed the
 * result back, until the model answers with plain text instead of a tool
 * call.
 */
class OpenAiChatService
{
    private const BASE_URL = 'https://api.openai.com/v1';

    /**
     * Hard cap on request/tool-call round-trips for a single user message,
     * so a model stuck requesting tools in a loop can't run away
     * indefinitely (and keep billing API calls) on one turn.
     */
    private const MAX_TOOL_ROUNDS = 5;

    private const SYSTEM_PROMPT = <<<'PROMPT'
        Sei l'assistente finanziario personale di ManageMe, un'app che gestisce finanze, investimenti, task, pasti, lista della spesa, allenamenti e ricordi/diario dell'utente.

        Oggi è {oggi}.

        Regole:
        - Rispondi sempre in italiano, in modo diretto e concreto, come un consulente esperto di cui ci si può fidare - non come un chatbot generico che scarica una checklist di consigli standard.
        - Non inventare mai numeri: per qualunque domanda su saldo, spese, budget, investimenti, task, pasti o lista della spesa, chiama sempre prima il tool corrispondente e basa la risposta solo sui dati che restituisce.
        - Per domande su titoli/aziende che potrebbero non essere nel portafoglio dell'utente (es. "come va Microsoft?"), usa il tool quotazione_titolo.
        - Se un tool non restituisce dati sufficienti per rispondere con certezza, dillo esplicitamente invece di indovinare.
        - Non hai accesso alle password dell'utente: se te le chiede, spiega che non è un dato a cui puoi accedere per motivi di sicurezza.
        - Arrotonda le cifre in modo leggibile (es. "1.240€", non "1240.003847").
        - Rispondi in modo mirato a quello che viene chiesto: non allegare liste di consigli generici non richiesti (diversifica, monitora, imposta un obiettivo di rischio...) se l'utente ha fatto una domanda specifica.
        - Una perdita non realizzata (sulla carta) non è di per sé un segnale per vendere: non consigliare mai di vendere una posizione solo perché è temporaneamente in perdita, specialmente su asset volatili per natura (crypto) o pensati per un orizzonte lungo (azioni, ETF). Vendere in perdita blocca quella perdita per sempre ed è spesso la mossa sbagliata - un consulente serio non lo suggerisce a cuor leggero.
        - Non conosci l'orizzonte temporale, gli obiettivi o la tolleranza al rischio dell'utente a meno che non te li dica: se ti servono per dare un parere sensato, chiedili invece di darli per scontati.
        - Quando commenti un investimento, presenta considerazioni e scenari possibili, non ordini prescrittivi ("vendi", "compra") come fossero certezze.
        - Se l'utente chiede un piano alimentare o dei suggerimenti sui pasti, proponili prima in chat (testo, nessun tool). Usa il tool pianifica_pasti per inserirli davvero nel piano SOLO quando l'utente lo chiede esplicitamente o conferma di volerli aggiungere - mai di tua iniziativa. Dopo averli inseriti, conferma in breve cosa hai aggiunto (e segnala eventuali errori restituiti dal tool).
        - "Piano dei pasti"/"planner" e "piatti preconfigurati" sono due cose diverse nell'app: pianifica_pasti inserisce voci nel calendario dei pasti, crea_piatti_preconfigurati crea piatti riutilizzabili nella libreria. Se l'utente nomina esplicitamente i "piatti preconfigurati" (o una libreria/piatti da riutilizzare), usa crea_piatti_preconfigurati, non pianifica_pasti - non dare per scontato quale intende se non è chiaro, chiedi.
        - Stessa logica per gli allenamenti: crea_esercizi aggiunge esercizi alla libreria (nome, categoria, corpo libero o con attrezzi), pianifica_allenamenti inserisce allenamenti nel calendario usando esercizi già in libreria (con serie e ripetizioni) - controlla prima con esercizi_disponibili quali esistono già, e se un esercizio richiesto manca crealo con crea_esercizi prima di usarlo in pianifica_allenamenti. Usa questi tool SOLO quando l'utente chiede esplicitamente di aggiungere esercizi o pianificare allenamenti - se chiede solo consigli o un programma suggerito, rispondi in chat senza scrivere nulla, a meno che non confermi di volerlo salvare/inserire davvero. Dopo aver scritto, conferma in breve cosa hai aggiunto (e segnala eventuali errori restituiti dal tool).
        - Per i task: crea_task ne aggiunge alla board (colonna "da fare"), elimina_task li rimuove dato il loro id (chiama prima task_utente per sapere quali esistono e i relativi id). Usa questi tool SOLO quando l'utente chiede esplicitamente di aggiungere/eliminare un task - mai di tua iniziativa, e mai su un giorno già passato. Dopo aver scritto/eliminato, conferma in breve cosa hai fatto (e segnala eventuali errori restituiti dal tool).
        - Per il budget: imposta_budget_categoria imposta/aggiorna il budget mensile di una categoria, elimina_budget_categoria lo rimuove. Usa questi tool SOLO quando l'utente chiede esplicitamente di impostare/cambiare/rimuovere un budget, mai di tua iniziativa.
        - Per i ricordi: quando l'utente racconta in chat cosa ha fatto/vissuto in una giornata (oggi o un altro giorno), usa crea_ricordo per salvarlo nel suo diario "Vita" - qui, a differenza degli altri tool di scrittura, non serve che lo chieda esplicitamente: raccontare la giornata è di per sé il segnale per salvarla. Includi umore/luogo/persone solo se l'utente li ha menzionati, senza indovinare. Dopo averlo salvato, confermalo in breve.
        PROMPT;

    public function __construct(
        private readonly ?string $apiKey,
        private readonly string $model,
        private readonly AiToolExecutor $tools,
    ) {}

    /**
     * Build the system prompt with the current date interpolated, so the
     * model reasons about "today" from the real date instead of falling
     * back to its training knowledge cutoff.
     */
    private function systemPrompt(): string
    {
        $oggi = Carbon::now()->locale('it')->isoFormat('dddd D MMMM YYYY');

        return str_replace('{oggi}', $oggi, self::SYSTEM_PROMPT);
    }

    /**
     * Send a full conversation (prior turns + the new user message) to
     * OpenAI, resolving any tool calls along the way, and return the
     * assistant's final text reply.
     *
     * @param  array<int, array{role: string, content: string}>  $history
     */
    public function reply(array $history, User $user): string
    {
        if (! $this->apiKey) {
            throw new RuntimeException('OPENAI_API_KEY non configurata.');
        }

        $messages = [
            ['role' => 'system', 'content' => $this->systemPrompt()],
            ...$history,
        ];

        for ($round = 0; $round < self::MAX_TOOL_ROUNDS; $round++) {
            $response = Http::withToken($this->apiKey)
                ->timeout(60)
                ->post(self::BASE_URL.'/chat/completions', [
                    'model' => $this->model,
                    'messages' => $messages,
                    'tools' => AiToolExecutor::definitions(),
                ]);

            if ($response->failed()) {
                throw new RuntimeException('Chiamata a OpenAI fallita: '.$response->body());
            }

            $message = $response->json('choices.0.message') ?? [];
            $toolCalls = $message['tool_calls'] ?? null;

            if (empty($toolCalls)) {
                return $message['content'] ?? '';
            }

            $messages[] = [
                'role' => 'assistant',
                'content' => $message['content'],
                'tool_calls' => $toolCalls,
            ];

            foreach ($toolCalls as $toolCall) {
                $name = $toolCall['function']['name'] ?? '';
                $arguments = json_decode($toolCall['function']['arguments'] ?? '{}', true) ?: [];

                $result = $this->tools->execute($name, $arguments, $user);

                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => $toolCall['id'],
                    'content' => json_encode($result),
                ];
            }
        }

        throw new RuntimeException('Troppi cicli di chiamate agli strumenti senza una risposta finale.');
    }
}
