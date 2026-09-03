<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<FinancialAccount, $this>
     */
    public function financialAccounts(): HasMany
    {
        return $this->hasMany(FinancialAccount::class);
    }

    /**
     * @return HasMany<Card, $this>
     */
    public function cards(): HasMany
    {
        return $this->hasMany(Card::class);
    }

    /**
     * @return HasMany<TransactionCategory, $this>
     */
    public function transactionCategories(): HasMany
    {
        return $this->hasMany(TransactionCategory::class);
    }

    /**
     * @return HasMany<CategoryRule, $this>
     */
    public function categoryRules(): HasMany
    {
        return $this->hasMany(CategoryRule::class);
    }

    /**
     * @return HasMany<InvestmentNote, $this>
     */
    public function investmentNotes(): HasMany
    {
        return $this->hasMany(InvestmentNote::class);
    }

    /**
     * @return HasMany<CompanyAnalysis, $this>
     */
    public function companyAnalyses(): HasMany
    {
        return $this->hasMany(CompanyAnalysis::class);
    }

    /**
     * @return HasMany<Investment, $this>
     */
    public function investments(): HasMany
    {
        return $this->hasMany(Investment::class);
    }

    /**
     * @return HasMany<PasswordGroup, $this>
     */
    public function passwordGroups(): HasMany
    {
        return $this->hasMany(PasswordGroup::class);
    }

    /**
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * @return HasMany<TaskBoard, $this>
     */
    public function taskBoards(): HasMany
    {
        return $this->hasMany(TaskBoard::class);
    }

    /**
     * Boards someone else owns and shared with this user.
     *
     * @return BelongsToMany<TaskBoard, $this>
     */
    public function sharedTaskBoards(): BelongsToMany
    {
        return $this->belongsToMany(TaskBoard::class, 'task_board_members')->withTimestamps();
    }

    /**
     * @return HasMany<ShoppingList, $this>
     */
    public function shoppingLists(): HasMany
    {
        return $this->hasMany(ShoppingList::class);
    }

    /**
     * Lists someone else owns and shared with this user.
     *
     * @return BelongsToMany<ShoppingList, $this>
     */
    public function sharedShoppingLists(): BelongsToMany
    {
        return $this->belongsToMany(ShoppingList::class, 'shopping_list_members')->withTimestamps();
    }

    /**
     * A meal plan has no table of its own - it *is* a user's meals - so the
     * sharing relations live here, on the owner, instead of on a container
     * model the way TaskBoard and ShoppingList do theirs.
     *
     * The people this user shared their own meal plan with.
     *
     * @return BelongsToMany<User, $this>
     */
    public function mealPlanMembers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'meal_plan_members', 'owner_user_id', 'member_user_id')->withTimestamps();
    }

    /**
     * The owners of the meal plans shared with this user.
     *
     * @return BelongsToMany<User, $this>
     */
    public function sharedMealPlans(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'meal_plan_members', 'member_user_id', 'owner_user_id')->withTimestamps();
    }

    /**
     * Everyone who plans and cooks from this user's meal plan, owner first -
     * the list behind both the sharing panel and the cook picker.
     *
     * @return Collection<int, User>
     */
    public function mealPlanPeople(): Collection
    {
        return collect([$this])->concat($this->mealPlanMembers)->values();
    }

    /**
     * Owner or invited member: the single check behind every meal action,
     * since members are deliberately as powerful as the owner on the plan's
     * meals (only sharing it further is owner-only).
     */
    public function mealPlanIsAccessibleBy(self $user): bool
    {
        return $this->id === $user->id
            || $this->mealPlanMembers()->whereKey($user->id)->exists();
    }

    /**
     * Un budget non ha una tabella propria - è l'insieme di categorie e mesi
     * di un utente - quindi le relazioni di condivisione stanno qui, come per
     * la pianificazione dei pasti.
     *
     * Le persone con cui questo utente ha condiviso il proprio budget.
     *
     * @return BelongsToMany<User, $this>
     */
    public function budgetMembers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'budget_members', 'owner_user_id', 'member_user_id')->withTimestamps();
    }

    /**
     * I proprietari dei budget condivisi con questo utente.
     *
     * @return BelongsToMany<User, $this>
     */
    public function sharedBudgets(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'budget_members', 'member_user_id', 'owner_user_id')->withTimestamps();
    }

    /**
     * Tutti quelli che lavorano su questo budget, proprietario per primo.
     *
     * @return Collection<int, User>
     */
    public function budgetPeople(): Collection
    {
        return collect([$this])->concat($this->budgetMembers)->values();
    }

    /**
     * Proprietario o invitato: il controllo dietro ogni azione sul budget.
     * Gli invitati sono potenti quanto il proprietario sui contenuti; solo
     * condividerlo ancora resta del proprietario.
     */
    public function budgetIsAccessibleBy(self $user): bool
    {
        return $this->id === $user->id
            || $this->budgetMembers()->whereKey($user->id)->exists();
    }

    /**
     * @return HasMany<Meal, $this>
     */
    public function meals(): HasMany
    {
        return $this->hasMany(Meal::class);
    }

    /**
     * @return HasMany<Dish, $this>
     */
    public function dishes(): HasMany
    {
        return $this->hasMany(Dish::class);
    }

    /**
     * @return HasMany<AiConversation, $this>
     */
    public function aiConversations(): HasMany
    {
        return $this->hasMany(AiConversation::class);
    }

    /**
     * @return HasMany<Workout, $this>
     */
    public function workouts(): HasMany
    {
        return $this->hasMany(Workout::class);
    }

    /**
     * @return HasMany<Exercise, $this>
     */
    public function exercises(): HasMany
    {
        return $this->hasMany(Exercise::class);
    }

    /**
     * @return HasMany<Memory, $this>
     */
    public function memories(): HasMany
    {
        return $this->hasMany(Memory::class);
    }

    /**
     * @return HasMany<Event, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    /**
     * @return HasOne<BalanceSheetProfile, $this>
     */
    public function balanceSheetProfile(): HasOne
    {
        return $this->hasOne(BalanceSheetProfile::class);
    }

    /**
     * @return HasMany<BalanceSheetEntry, $this>
     */
    public function balanceSheetEntries(): HasMany
    {
        return $this->hasMany(BalanceSheetEntry::class);
    }

    /**
     * @return HasMany<BalanceSheetMonthClosure, $this>
     */
    public function balanceSheetMonthClosures(): HasMany
    {
        return $this->hasMany(BalanceSheetMonthClosure::class);
    }

    /**
     * @return HasMany<BudgetCategory, $this>
     */
    public function budgetCategories(): HasMany
    {
        return $this->hasMany(BudgetCategory::class);
    }

    /**
     * @return HasMany<MonthlyBudget, $this>
     */
    public function monthlyBudgets(): HasMany
    {
        return $this->hasMany(MonthlyBudget::class);
    }
}
