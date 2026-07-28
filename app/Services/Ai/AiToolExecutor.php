<?php

namespace App\Services\Ai;

use App\Contracts\MarketPriceProvider;
use App\Models\Card;
use App\Models\Dish;
use App\Models\Exercise;
use App\Models\Meal;
use App\Models\ShoppingList;
use App\Models\Task;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutExercise;
use App\Services\Finance\AccountBalanceCalculator;
use App\Services\Finance\InvestmentPositionCalculator;
use App\Services\Finance\SpendingSummaryCalculator;
use App\Services\Meals\DishCategories;
use App\Services\Shopping\GroceryCategories;
use App\Services\Workouts\ExerciseCategories;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Everything the AI assistant is allowed to know about, and do to, a user's
 * data - an explicit allowlist of "tools" the model can call, each scoped to
 * the authenticated user by this class - never by an id the model itself
 * supplies. Passwords are deliberately not represented by any tool here, the
 * same way PasswordGroupController::index() never puts them on a page's
 * props - excluded by omission, not by a check that could be missed.
 *
 * Only four tools write (pianifica_pasti, crea_piatti_preconfigurati,
 * crea_esercizi, pianifica_allenamenti). Writing is deliberately kept to
 * these low-stakes domains for now - meal plan entries, dish-library rows,
 * exercise-library rows and workout entries are easy to review and undo
 * from their respective pages, unlike e.g. a financial transaction or a
 * task the model might get subtly wrong.
 */
class AiToolExecutor
{
    public function __construct(
        private readonly AccountBalanceCalculator $balanceCalculator,
        private readonly SpendingSummaryCalculator $spendingCalculator,
        private readonly InvestmentPositionCalculator $positionCalculator,
        private readonly MarketPriceProvider $marketPriceProvider,
    ) {}

    /**
     * OpenAI "tools" definitions (function-calling schema) for every tool
     * this executor supports.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function definitions(): array
    {
        return [
            self::definition('saldo_conto', 'Restituisce il saldo attuale totale dei conti/carte dell\'utente.'),
            self::definition(
                'spese_per_categoria',
                'Spesa effettiva per categoria in un dato mese, confrontata con il budget mensile impostato dall\'utente per quella categoria.',
                [
                    'mese' => ['type' => 'string', 'description' => 'Mese nel formato YYYY-MM. Se omesso, usa il mese corrente.'],
                ],
            ),
            self::definition(
                'posizioni_investimenti',
                'Le posizioni di investimento aperte e chiuse dell\'utente, con valore investito, valore di mercato e guadagno/perdita.',
            ),
            self::definition(
                'transazioni_recenti',
                'Le transazioni più recenti dell\'utente (spese e entrate), opzionalmente filtrate per categoria.',
                [
                    'giorni' => ['type' => 'integer', 'description' => 'Quanti giorni indietro guardare (default 30).'],
                    'categoria' => ['type' => 'string', 'description' => 'Nome categoria per filtrare (opzionale).'],
                ],
            ),
            self::definition(
                'task_utente',
                'L\'elenco dei task/cose da fare dell\'utente, opzionalmente filtrati per stato.',
                [
                    'stato' => ['type' => 'string', 'enum' => Task::STATUSES, 'description' => 'Filtra per stato (opzionale).'],
                ],
            ),
            self::definition(
                'pasti_pianificati',
                'I pasti pianificati dall\'utente nei prossimi giorni (pranzi e cene).',
                [
                    'giorni' => ['type' => 'integer', 'description' => 'Quanti giorni in avanti guardare (default 7).'],
                ],
            ),
            self::definition(
                'lista_della_spesa',
                'I prodotti ancora da comprare nelle liste della spesa dell\'utente.',
            ),
            self::definition(
                'piatti_preconfigurati',
                'I piatti nella libreria "piatti preconfigurati" dell\'utente (riutilizzabili quando pianifica i pasti), con i relativi ingredienti - diversi dai pasti già inseriti nel calendario (vedi pasti_pianificati).',
            ),
            self::definition(
                'esercizi_disponibili',
                'Gli esercizi nella libreria dell\'utente (nome, categoria, se richiedono attrezzi o si fanno a corpo libero) - da usare come esercizi disponibili quando si pianificano gli allenamenti con pianifica_allenamenti.',
            ),
            self::definition(
                'allenamenti_pianificati',
                'Gli allenamenti pianificati dall\'utente nei prossimi giorni, con gli esercizi (serie e ripetizioni) e quante serie sono già state completate.',
                [
                    'giorni' => ['type' => 'integer', 'description' => 'Quanti giorni in avanti guardare (default 7).'],
                ],
            ),
            self::definition(
                'quotazione_titolo',
                'Cerca un\'azienda o un titolo per nome o ticker e restituisce l\'ultimo prezzo disponibile (dati di mercato esterni, non legati al portafoglio dell\'utente).',
                [
                    'nome_o_ticker' => ['type' => 'string', 'description' => 'Es. "Microsoft" oppure "MSFT".'],
                ],
                required: ['nome_o_ticker'],
            ),
            self::definition(
                'pianifica_pasti',
                'Inserisce uno o più pasti nel piano alimentare dell\'utente (li fa comparire nella pagina Pasti). Usalo SOLO quando l\'utente chiede esplicitamente di aggiungere/inserire i pasti, non per suggerire un piano in chat.',
                [
                    'pasti' => [
                        'type' => 'array',
                        'description' => 'Elenco dei pasti da inserire.',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'data' => ['type' => 'string', 'description' => 'Data del pasto, formato YYYY-MM-DD.'],
                                'tipo' => ['type' => 'string', 'enum' => Meal::MEAL_TYPES, 'description' => 'lunch (pranzo) o dinner (cena).'],
                                'titolo' => ['type' => 'string', 'description' => 'Nome del piatto/pasto.'],
                                'descrizione' => ['type' => 'string', 'description' => 'Dettagli opzionali, es. ingredienti o breve ricetta.'],
                                'categoria' => ['type' => 'string', 'enum' => DishCategories::keys(), 'description' => 'Categoria del piatto (opzionale).'],
                            ],
                            'required' => ['data', 'tipo', 'titolo'],
                        ],
                    ],
                ],
                required: ['pasti'],
            ),
            self::definition(
                'crea_piatti_preconfigurati',
                'Crea uno o più piatti nella libreria "piatti preconfigurati" dell\'utente (riutilizzabili in futuro quando pianifica i pasti) - NON li inserisce nel calendario dei pasti, per quello usa pianifica_pasti. Usalo SOLO quando l\'utente chiede esplicitamente di salvarli/aggiungerli nella libreria/nei piatti preconfigurati.',
                [
                    'piatti' => [
                        'type' => 'array',
                        'description' => 'Elenco dei piatti da creare nella libreria.',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'nome' => ['type' => 'string', 'description' => 'Nome del piatto.'],
                                'descrizione' => ['type' => 'string', 'description' => 'Dettagli opzionali.'],
                                'categoria' => ['type' => 'string', 'enum' => DishCategories::keys(), 'description' => 'Categoria del piatto.'],
                                'ingredienti' => [
                                    'type' => 'array',
                                    'description' => 'Ingredienti opzionali del piatto.',
                                    'items' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'nome' => ['type' => 'string'],
                                            'categoria' => ['type' => 'string', 'enum' => GroceryCategories::keys(), 'description' => 'Categoria per la lista della spesa (opzionale).'],
                                        ],
                                        'required' => ['nome'],
                                    ],
                                ],
                            ],
                            'required' => ['nome', 'categoria'],
                        ],
                    ],
                ],
                required: ['piatti'],
            ),
            self::definition(
                'crea_esercizi',
                'Crea uno o più esercizi nella libreria "esercizi" dell\'utente (riutilizzabili in futuro quando pianifica gli allenamenti con pianifica_allenamenti) - NON li inserisce in un allenamento, per quello usa pianifica_allenamenti dopo averli creati. Usalo SOLO quando l\'utente chiede esplicitamente di aggiungerli/salvarli nella libreria esercizi.',
                [
                    'esercizi' => [
                        'type' => 'array',
                        'description' => 'Elenco degli esercizi da creare nella libreria.',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'nome' => ['type' => 'string', 'description' => 'Nome dell\'esercizio.'],
                                'categoria' => ['type' => 'string', 'enum' => ExerciseCategories::keys(), 'description' => 'Categoria (gruppo muscolare) dell\'esercizio.'],
                                'corpo_libero' => ['type' => 'boolean', 'description' => 'true se l\'esercizio si fa a corpo libero (default), false se richiede attrezzi.'],
                            ],
                            'required' => ['nome', 'categoria'],
                        ],
                    ],
                ],
                required: ['esercizi'],
            ),
            self::definition(
                'pianifica_allenamenti',
                'Inserisce uno o più allenamenti nel piano settimanale dell\'utente (li fa comparire nella pagina Allenamenti), ciascuno con uno o più esercizi con serie e ripetizioni. Ogni esercizio nominato deve già esistere nella libreria dell\'utente (vedi esercizi_disponibili) - se manca, crealo prima con crea_esercizi. Usalo SOLO quando l\'utente chiede esplicitamente di pianificare/inserire gli allenamenti, non per suggerire un piano in chat.',
                [
                    'allenamenti' => [
                        'type' => 'array',
                        'description' => 'Elenco degli allenamenti da inserire, uno per giorno.',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'data' => ['type' => 'string', 'description' => 'Data dell\'allenamento, formato YYYY-MM-DD.'],
                                'titolo' => ['type' => 'string', 'description' => 'Nome opzionale dell\'allenamento.'],
                                'esercizi' => [
                                    'type' => 'array',
                                    'description' => 'Esercizi dell\'allenamento, con serie e ripetizioni.',
                                    'items' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'nome' => ['type' => 'string', 'description' => 'Nome dell\'esercizio, deve corrispondere a uno già nella libreria.'],
                                            'serie' => ['type' => 'integer', 'description' => 'Numero di serie.'],
                                            'ripetizioni' => ['type' => 'integer', 'description' => 'Numero di ripetizioni per serie.'],
                                        ],
                                        'required' => ['nome', 'serie', 'ripetizioni'],
                                    ],
                                ],
                            ],
                            'required' => ['data', 'esercizi'],
                        ],
                    ],
                ],
                required: ['allenamenti'],
            ),
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $properties
     * @param  array<int, string>  $required
     * @return array<string, mixed>
     */
    private static function definition(string $name, string $description, array $properties = [], array $required = []): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $name,
                'description' => $description,
                'parameters' => [
                    'type' => 'object',
                    'properties' => $properties === [] ? (object) [] : $properties,
                    'required' => $required,
                ],
            ],
        ];
    }

    /**
     * Run one tool call for the given user. Always returns an array (never
     * throws) so a single failing tool call can be reported back to the
     * model as an error message instead of blowing up the whole turn.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function execute(string $tool, array $arguments, User $user): array
    {
        return match ($tool) {
            'saldo_conto' => $this->saldoConto($user),
            'spese_per_categoria' => $this->spesePerCategoria($user, $arguments),
            'posizioni_investimenti' => $this->posizioniInvestimenti($user),
            'transazioni_recenti' => $this->transazioniRecenti($user, $arguments),
            'task_utente' => $this->taskUtente($user, $arguments),
            'pasti_pianificati' => $this->pastiPianificati($user, $arguments),
            'lista_della_spesa' => $this->listaDellaSpesa($user),
            'piatti_preconfigurati' => $this->piattiPreconfigurati($user),
            'esercizi_disponibili' => $this->eserciziDisponibili($user),
            'allenamenti_pianificati' => $this->allenamentiPianificati($user, $arguments),
            'quotazione_titolo' => $this->quotazioneTitolo($arguments),
            'pianifica_pasti' => $this->pianificaPasti($user, $arguments),
            'crea_piatti_preconfigurati' => $this->creaPiattiPreconfigurati($user, $arguments),
            'crea_esercizi' => $this->creaEsercizi($user, $arguments),
            'pianifica_allenamenti' => $this->pianificaAllenamenti($user, $arguments),
            default => ['errore' => "Tool sconosciuto: {$tool}"],
        };
    }

    /**
     * @return Collection<int, Card>
     */
    private function userCards(User $user): Collection
    {
        return Card::query()->where('user_id', $user->id)->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function saldoConto(User $user): array
    {
        return ['saldo_eur' => $this->balanceCalculator->totalFor($this->userCards($user))];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function spesePerCategoria(User $user, array $arguments): array
    {
        $month = $arguments['mese'] ?? null;

        return ['categorie' => $this->spendingCalculator->calculate($user, $this->userCards($user), $month)];
    }

    /**
     * @return array<string, mixed>
     */
    private function posizioniInvestimenti(User $user): array
    {
        $investmentCategoryIds = TransactionCategory::query()
            ->where(fn ($query) => $query->whereNull('user_id')->orWhere('user_id', $user->id))
            ->where('name', 'Investimenti')
            ->pluck('id');

        $investmentCardIds = $this->userCards($user)->where('is_investment_card', true)->pluck('id');

        $transactions = Transaction::query()
            ->whereIn('card_id', $investmentCardIds)
            ->whereIn('transaction_category_id', $investmentCategoryIds)
            ->get(['transaction_date', 'amount', 'direction', 'card_id', 'isin', 'quantity', 'description']);

        return $this->positionCalculator->calculate($transactions);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function transazioniRecenti(User $user, array $arguments): array
    {
        $days = (int) ($arguments['giorni'] ?? 30);
        $categoryName = $arguments['categoria'] ?? null;

        $query = Transaction::query()
            ->whereIn('card_id', $this->userCards($user)->pluck('id'))
            ->where('transaction_date', '>=', Carbon::now()->subDays($days)->toDateString())
            ->with('category')
            ->orderByDesc('transaction_date');

        if ($categoryName !== null) {
            $query->whereHas('category', fn ($q) => $q->where('name', 'like', "%{$categoryName}%"));
        }

        return [
            'transazioni' => $query->limit(150)->get()->map(fn (Transaction $transaction) => [
                'data' => $transaction->transaction_date->format('Y-m-d'),
                'descrizione' => $transaction->description,
                'importo' => (float) $transaction->amount,
                'direzione' => $transaction->direction,
                'categoria' => $transaction->category?->name,
            ])->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function taskUtente(User $user, array $arguments): array
    {
        $query = Task::query()->where('user_id', $user->id)->orderBy('task_date');

        if (! empty($arguments['stato'])) {
            $query->where('status', $arguments['stato']);
        }

        return [
            'task' => $query->get()->map(fn (Task $task) => [
                'titolo' => $task->title,
                'stato' => $task->status,
                'data' => $task->task_date->format('Y-m-d'),
            ])->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function pastiPianificati(User $user, array $arguments): array
    {
        $days = (int) ($arguments['giorni'] ?? 7);

        $meals = Meal::query()
            ->where('user_id', $user->id)
            ->whereBetween('meal_date', [Carbon::now()->toDateString(), Carbon::now()->addDays($days)->toDateString()])
            ->orderBy('meal_date')
            ->get();

        return [
            'pasti' => $meals->map(fn (Meal $meal) => [
                'data' => $meal->meal_date->format('Y-m-d'),
                'tipo' => $meal->meal_type,
                'titolo' => $meal->title,
            ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function listaDellaSpesa(User $user): array
    {
        $lists = ShoppingList::query()
            ->where('user_id', $user->id)
            ->with(['items' => fn ($query) => $query->where('purchased', false)])
            ->get();

        return [
            'liste' => $lists
                ->map(fn (ShoppingList $list) => [
                    'nome' => $list->name,
                    'prodotti' => $list->items->pluck('name')->all(),
                ])
                ->filter(fn (array $list) => count($list['prodotti']) > 0)
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function piattiPreconfigurati(User $user): array
    {
        $dishes = $user->dishes()->with('ingredients')->orderBy('name')->get();

        return [
            'piatti' => $dishes->map(fn (Dish $dish) => [
                'nome' => $dish->name,
                'categoria' => $dish->category,
                'descrizione' => $dish->description,
                'ingredienti' => $dish->ingredients->pluck('name')->all(),
            ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function eserciziDisponibili(User $user): array
    {
        $exercises = $user->exercises()->orderBy('name')->get();

        return [
            'esercizi' => $exercises->map(fn (Exercise $exercise) => [
                'nome' => $exercise->name,
                'categoria' => $exercise->category,
                'corpo_libero' => ! $exercise->requires_equipment,
            ])->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function allenamentiPianificati(User $user, array $arguments): array
    {
        $days = (int) ($arguments['giorni'] ?? 7);

        $workouts = $user->workouts()
            ->whereBetween('workout_date', [Carbon::now()->toDateString(), Carbon::now()->addDays($days)->toDateString()])
            ->with(['exercises.exercise', 'exercises.sets'])
            ->orderBy('workout_date')
            ->get();

        return [
            'allenamenti' => $workouts->map(fn (Workout $workout) => [
                'data' => $workout->workout_date->format('Y-m-d'),
                'titolo' => $workout->title,
                'esercizi' => $workout->exercises->map(fn (WorkoutExercise $workoutExercise) => [
                    'nome' => $workoutExercise->exercise->name,
                    'serie' => $workoutExercise->sets_count,
                    'ripetizioni' => $workoutExercise->reps_count,
                    'serie_completate' => $workoutExercise->sets->where('completed', true)->count(),
                ])->all(),
            ])->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function quotazioneTitolo(array $arguments): array
    {
        $query = $arguments['nome_o_ticker'] ?? null;

        if (! $query) {
            return ['errore' => 'nome_o_ticker mancante'];
        }

        // resolveSymbol()'s own parameter is named "$isin", but it forwards
        // straight to EODHD's /search/{query} endpoint, which resolves free
        // text (a company name or a ticker) just as well as an ISIN - no
        // separate "search by name" call exists or is needed.
        $resolved = $this->marketPriceProvider->resolveSymbol($query);

        if ($resolved === null) {
            return ['errore' => "Nessun titolo trovato per \"{$query}\"."];
        }

        $price = $this->marketPriceProvider->fetchPrice($resolved->code, $resolved->exchange);

        if ($price === null) {
            return ['errore' => "Titolo trovato ({$resolved->name}) ma prezzo non disponibile al momento."];
        }

        return [
            'nome' => $resolved->name,
            'ticker' => "{$resolved->code}.{$resolved->exchange}",
            'valuta' => $resolved->currency,
            'ultimo_prezzo' => $price->price,
            'data_prezzo' => $price->date->format('Y-m-d'),
        ];
    }

    /**
     * Creates one or more meals, mirroring MealController::store()'s own
     * append-to-end-of-day/slot positioning exactly (called once per meal in
     * the batch, in order, so two meals landing on the same day+slot in one
     * call still get consecutive positions instead of colliding on the
     * same one). Invalid entries are skipped and reported back individually
     * rather than failing the whole batch - a model-generated date/type is
     * exactly the kind of input worth double-checking before writing.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function pianificaPasti(User $user, array $arguments): array
    {
        $requested = $arguments['pasti'] ?? [];

        if (! is_array($requested) || $requested === []) {
            return ['errore' => 'Nessun pasto fornito.'];
        }

        $created = [];
        $errors = [];

        foreach ($requested as $index => $meal) {
            $date = $meal['data'] ?? null;
            $type = $meal['tipo'] ?? null;
            $title = $meal['titolo'] ?? null;
            $category = $meal['categoria'] ?? null;

            if (! is_string($date) || ! is_string($title) || $title === '' || ! in_array($type, Meal::MEAL_TYPES, true)) {
                $errors[] = "Pasto #{$index}: dati mancanti o non validi (servono data, tipo e titolo).";

                continue;
            }

            try {
                $date = Carbon::createFromFormat('Y-m-d', $date)->toDateString();
            } catch (Throwable) {
                $errors[] = "Pasto #{$index}: data \"{$date}\" non valida, atteso formato YYYY-MM-DD.";

                continue;
            }

            if (! is_string($category) || ! array_key_exists($category, DishCategories::ALL)) {
                $category = null;
            }

            // A model can (and in practice sometimes does) call this tool
            // more than once for the same request - retried rounds, or two
            // near-identical tool_calls in one response. Skipping an exact
            // title+date+slot match already on file, including ones just
            // created earlier in this same batch, is cheap insurance against
            // duplicate meals from a single "add this to my plan" ask.
            $alreadyExists = $user->meals()
                ->whereDate('meal_date', $date)
                ->where('meal_type', $type)
                ->where('title', $title)
                ->exists();

            if ($alreadyExists) {
                $errors[] = "Pasto #{$index}: \"{$title}\" il {$date} ({$type}) è già presente, non duplicato.";

                continue;
            }

            $nextPosition = 1 + ($user->meals()
                ->whereDate('meal_date', $date)
                ->where('meal_type', $type)
                ->max('position') ?? -1);

            $created[] = $user->meals()->create([
                'title' => $title,
                'description' => is_string($meal['descrizione'] ?? null) ? $meal['descrizione'] : null,
                'meal_date' => $date,
                'meal_type' => $type,
                'category' => $category,
                'position' => $nextPosition,
            ]);
        }

        return [
            'inseriti' => count($created),
            'pasti_inseriti' => collect($created)->map(fn (Meal $meal) => [
                'data' => $meal->meal_date->format('Y-m-d'),
                'tipo' => $meal->meal_type,
                'titolo' => $meal->title,
            ])->all(),
            'errori' => $errors,
        ];
    }

    /**
     * Creates one or more dishes in the user's reusable "piatti
     * preconfigurati" library (App\Models\Dish) - a separate resource from
     * meal-plan entries (see pianificaPasti()), mirroring
     * DishController::store()'s own field set and its lenient
     * ingredient-category fallback to "altro". Skips a name already in the
     * user's library (case-sensitive match, same as the DB) for the same
     * duplicate-call protection pianificaPasti() has.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function creaPiattiPreconfigurati(User $user, array $arguments): array
    {
        $requested = $arguments['piatti'] ?? [];

        if (! is_array($requested) || $requested === []) {
            return ['errore' => 'Nessun piatto fornito.'];
        }

        $created = [];
        $errors = [];

        foreach ($requested as $index => $dish) {
            $name = $dish['nome'] ?? null;
            $category = $dish['categoria'] ?? null;

            if (! is_string($name) || $name === '' || ! is_string($category) || ! array_key_exists($category, DishCategories::ALL)) {
                $errors[] = "Piatto #{$index}: nome e categoria (valida) sono obbligatori.";

                continue;
            }

            if ($user->dishes()->where('name', $name)->exists()) {
                $errors[] = "Piatto #{$index}: \"{$name}\" è già nella libreria, non duplicato.";

                continue;
            }

            $newDish = $user->dishes()->create([
                'name' => $name,
                'description' => is_string($dish['descrizione'] ?? null) ? $dish['descrizione'] : null,
                'category' => $category,
            ]);

            $ingredients = is_array($dish['ingredienti'] ?? null) ? $dish['ingredienti'] : [];

            foreach ($ingredients as $ingredient) {
                $ingredientName = $ingredient['nome'] ?? null;

                if (! is_string($ingredientName) || $ingredientName === '') {
                    continue;
                }

                $ingredientCategory = $ingredient['categoria'] ?? null;

                $newDish->ingredients()->create([
                    'name' => $ingredientName,
                    'category' => (is_string($ingredientCategory) && array_key_exists($ingredientCategory, GroceryCategories::ALL))
                        ? $ingredientCategory
                        : 'altro',
                ]);
            }

            $created[] = $newDish;
        }

        return [
            'inseriti' => count($created),
            'piatti_inseriti' => collect($created)->map(fn (Dish $dish) => [
                'nome' => $dish->name,
                'categoria' => $dish->category,
            ])->all(),
            'errori' => $errors,
        ];
    }

    /**
     * Creates one or more exercises in the user's reusable "esercizi"
     * library (App\Models\Exercise), mirroring ExerciseController::store()'s
     * own field set. Skips a name already in the user's library (same
     * duplicate-call protection as creaPiattiPreconfigurati()).
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function creaEsercizi(User $user, array $arguments): array
    {
        $requested = $arguments['esercizi'] ?? [];

        if (! is_array($requested) || $requested === []) {
            return ['errore' => 'Nessun esercizio fornito.'];
        }

        $created = [];
        $errors = [];

        foreach ($requested as $index => $exercise) {
            $name = $exercise['nome'] ?? null;
            $category = $exercise['categoria'] ?? null;

            if (! is_string($name) || $name === '' || ! is_string($category) || ! array_key_exists($category, ExerciseCategories::ALL)) {
                $errors[] = "Esercizio #{$index}: nome e categoria (valida) sono obbligatori.";

                continue;
            }

            if ($user->exercises()->where('name', $name)->exists()) {
                $errors[] = "Esercizio #{$index}: \"{$name}\" è già nella libreria, non duplicato.";

                continue;
            }

            $requiresEquipment = ! (is_bool($exercise['corpo_libero'] ?? null) ? $exercise['corpo_libero'] : true);

            $created[] = $user->exercises()->create([
                'name' => $name,
                'category' => $category,
                'requires_equipment' => $requiresEquipment,
            ]);
        }

        return [
            'inseriti' => count($created),
            'esercizi_inseriti' => collect($created)->map(fn (Exercise $exercise) => [
                'nome' => $exercise->name,
                'categoria' => $exercise->category,
                'corpo_libero' => ! $exercise->requires_equipment,
            ])->all(),
            'errori' => $errors,
        ];
    }

    /**
     * Creates one or more workouts, mirroring WorkoutController::store()'s
     * own find-or-create-the-day's-workout and append-exercises logic
     * (called once per workout in the batch), including generating each
     * exercise's individual WorkoutSet rows. Each exercise must already
     * exist in the user's library - matched by name (case-insensitive) -
     * so a model that hasn't created it yet gets a clear error back instead
     * of silently guessing a category/equipment flag for a new one.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function pianificaAllenamenti(User $user, array $arguments): array
    {
        $requested = $arguments['allenamenti'] ?? [];

        if (! is_array($requested) || $requested === []) {
            return ['errore' => 'Nessun allenamento fornito.'];
        }

        $exercisesByName = $user->exercises()->get()->keyBy(fn (Exercise $exercise) => mb_strtolower($exercise->name));

        $created = [];
        $errors = [];

        foreach ($requested as $index => $workoutData) {
            $date = $workoutData['data'] ?? null;
            $exercises = $workoutData['esercizi'] ?? [];

            if (! is_string($date) || ! is_array($exercises) || $exercises === []) {
                $errors[] = "Allenamento #{$index}: servono data ed esercizi.";

                continue;
            }

            try {
                $date = Carbon::createFromFormat('Y-m-d', $date)->toDateString();
            } catch (Throwable) {
                $errors[] = "Allenamento #{$index}: data \"{$date}\" non valida, atteso formato YYYY-MM-DD.";

                continue;
            }

            $workout = $user->workouts()->whereDate('workout_date', $date)->first();

            if (! $workout) {
                $workout = $user->workouts()->create([
                    'workout_date' => $date,
                    'title' => is_string($workoutData['titolo'] ?? null) ? $workoutData['titolo'] : null,
                ]);
            }

            $nextPosition = 1 + ($workout->exercises()->max('position') ?? -1);
            $addedExercises = [];

            foreach ($exercises as $exerciseIndex => $exerciseData) {
                $name = $exerciseData['nome'] ?? null;
                $sets = $exerciseData['serie'] ?? null;
                $reps = $exerciseData['ripetizioni'] ?? null;

                if (! is_string($name) || ! is_int($sets) || $sets < 1 || ! is_int($reps) || $reps < 1) {
                    $errors[] = "Allenamento #{$index}, esercizio #{$exerciseIndex}: servono nome, serie e ripetizioni valide.";

                    continue;
                }

                $exercise = $exercisesByName->get(mb_strtolower($name));

                if (! $exercise) {
                    $errors[] = "Allenamento #{$index}: esercizio \"{$name}\" non trovato nella libreria - crealo prima con crea_esercizi.";

                    continue;
                }

                if ($workout->exercises()->where('exercise_id', $exercise->id)->exists()) {
                    $errors[] = "Allenamento #{$index}: \"{$name}\" è già in questo allenamento, non duplicato.";

                    continue;
                }

                $workoutExercise = $workout->exercises()->create([
                    'exercise_id' => $exercise->id,
                    'sets_count' => $sets,
                    'reps_count' => $reps,
                    'position' => $nextPosition++,
                ]);

                for ($setNumber = 1; $setNumber <= $sets; $setNumber++) {
                    $workoutExercise->sets()->create(['set_number' => $setNumber]);
                }

                $addedExercises[] = ['nome' => $exercise->name, 'serie' => $sets, 'ripetizioni' => $reps];
            }

            if ($addedExercises !== []) {
                $created[] = ['data' => $date, 'esercizi' => $addedExercises];
            }
        }

        return [
            'inseriti' => count($created),
            'allenamenti_inseriti' => $created,
            'errori' => $errors,
        ];
    }
}
