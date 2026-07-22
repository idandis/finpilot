<?php

namespace App\Services\Finance;

use App\Models\CategoryRule;
use App\Models\Transaction;

class TransactionCategorizer
{
    /**
     * Match a transaction description against the user's active rules
     * (highest priority, then most-applied, then most recent, wins) and
     * return the matched category id, or null if nothing matches.
     */
    public function categorize(string $description, int $userId): ?int
    {
        $description = mb_strtolower($description);

        $rule = CategoryRule::query()
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->orderByDesc('priority')
            ->orderByDesc('times_applied')
            ->orderByDesc('id')
            ->get()
            ->first(fn (CategoryRule $rule) => str_contains($description, mb_strtolower($rule->pattern)));

        return $rule?->transaction_category_id;
    }

    /**
     * Learn from a manual categorization: create or reinforce a rule based on
     * the most significant word in the transaction's description, so the next
     * transaction from the same merchant is categorized automatically.
     */
    public function learnFromCorrection(Transaction $transaction, int $categoryId, int $userId): void
    {
        $keyword = $this->extractKeyword($transaction->description);

        if (! $keyword || mb_strlen($keyword) < 3) {
            return;
        }

        $rule = CategoryRule::query()
            ->where('user_id', $userId)
            ->whereRaw('LOWER(pattern) = ?', [$keyword])
            ->first();

        if ($rule) {
            $rule->transaction_category_id = $categoryId;
            $rule->times_applied = $rule->times_applied + 1;
            $rule->save();

            return;
        }

        CategoryRule::create([
            'user_id' => $userId,
            'transaction_category_id' => $categoryId,
            'pattern' => $keyword,
            'priority' => 0,
            'times_applied' => 1,
            'is_active' => true,
        ]);
    }

    /**
     * Pick the longest run of letters in the description as its most
     * distinctive word (usually the merchant name), e.g. "WWW.AMAZON.IT
     * +14018657948" -> "amazon", "Revolut**3251* Dublin" -> "revolut".
     */
    private function extractKeyword(string $description): ?string
    {
        preg_match_all('/[\p{L}]+/u', $description, $matches);
        $words = $matches[0] ?? [];

        if (empty($words)) {
            return null;
        }

        usort($words, fn (string $a, string $b) => mb_strlen($b) <=> mb_strlen($a));

        return mb_strtolower($words[0]);
    }
}
