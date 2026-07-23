<?php

namespace App\Services\Finance;

use App\Models\Card;
use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class TransactionRowPersister
{
    public function __construct(private readonly TransactionCategorizer $categorizer) {}

    /**
     * Persist a stream of normalized transaction rows (shared by every
     * importer, whatever the source file format), applying dedup and
     * automatic categorization consistently. Every import goes through a
     * specific card - the card's own (optional) linked account is recorded
     * on the row only as context, never used for dedup/scoping.
     *
     * @param  iterable<array{date: CarbonImmutable, description: string, amount: float, direction: string, category_id: int|null, isin?: string|null, quantity?: float|null}>  $rows
     * @return array{imported: int, duplicates: int, latest_year: int|null, latest_month: int|null}
     */
    public function persist(Card $card, iterable $rows): array
    {
        $imported = 0;
        $duplicates = 0;
        $latestDate = null;

        // Counts how many times an identical (date+amount+description) signature has
        // been seen so far in this import, since real statements can legitimately
        // contain two distinct transactions that look identical (e.g. two top-ups
        // of the same amount on the same day, with no other distinguishing data).
        $occurrenceIndex = [];

        DB::transaction(function () use ($card, $rows, &$imported, &$duplicates, &$occurrenceIndex, &$latestDate) {
            foreach ($rows as $row) {
                $date = $row['date'];
                $description = $row['description'];
                $absoluteAmount = $row['amount'];
                $direction = $row['direction'];

                if (! $latestDate || $date->greaterThan($latestDate)) {
                    $latestDate = $date;
                }

                $signature = self::buildSignature($card->id, $date, $direction, $absoluteAmount, $description);

                // If this exact signature repeats within the file, each repetition is
                // treated as its own occurrence: the Nth time we see it here is compared
                // against the Nth time it was ever imported, so genuinely repeated
                // transactions are kept while true re-imports are still recognised as
                // duplicates.
                $occurrence = $occurrenceIndex[$signature] ?? 0;
                $occurrenceIndex[$signature] = $occurrence + 1;

                $dedupHash = self::hashSignature($signature, $occurrence);

                $exists = Transaction::query()
                    ->where('card_id', $card->id)
                    ->where('dedup_hash', $dedupHash)
                    ->exists();

                if ($exists) {
                    $duplicates++;

                    continue;
                }

                $categoryId = $row['category_id'] ?? $this->categorizer->categorize($description, $card->user_id);

                try {
                    Transaction::create([
                        'financial_account_id' => $card->financial_account_id,
                        'card_id' => $card->id,
                        'transaction_category_id' => $categoryId,
                        'transaction_date' => $date,
                        'description' => $description,
                        'isin' => $row['isin'] ?? null,
                        'quantity' => $row['quantity'] ?? null,
                        'amount' => $absoluteAmount,
                        'direction' => $direction,
                        'dedup_hash' => $dedupHash,
                    ]);

                    $imported++;
                } catch (UniqueConstraintViolationException) {
                    // The (card_id, dedup_hash) unique constraint is the final safety net:
                    // if this exact row was inserted a moment ago by a concurrent request
                    // (e.g. a double-clicked submit), treat it as a duplicate instead of
                    // surfacing a database error.
                    $duplicates++;
                }
            }
        });

        return [
            'imported' => $imported,
            'duplicates' => $duplicates,
            'latest_year' => $latestDate ? (int) $latestDate->format('Y') : null,
            'latest_month' => $latestDate ? (int) $latestDate->format('n') : null,
        ];
    }

    /**
     * Build the identity signature for a transaction: card, calendar day
     * (the `transaction_date` column is a DATE with no time, so hashing a
     * parsed time-of-day here would make the hash impossible to reproduce
     * later from the stored row), direction, amount and description.
     *
     * Two distinct transactions can share all of this (e.g. two top-ups of
     * the same amount on the same day) - that's what the occurrence index
     * in `persist()` is for; it doesn't need time-of-day to tell them apart.
     */
    public static function buildSignature(int $cardId, \DateTimeInterface $date, string $direction, float $absoluteAmount, string $description): string
    {
        return implode('|', [
            $cardId,
            $date->format('Y-m-d'),
            $direction,
            number_format($absoluteAmount, 2, '.', ''),
            mb_strtolower(trim($description)),
        ]);
    }

    /**
     * Turn a signature plus its occurrence index (0, 1, 2... for repeated
     * identical-looking rows) into the stored dedup_hash.
     */
    public static function hashSignature(string $signature, int $occurrence): string
    {
        return hash('sha256', $signature.'|'.$occurrence);
    }
}
