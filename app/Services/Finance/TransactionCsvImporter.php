<?php

namespace App\Services\Finance;

use App\Models\FinancialAccount;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use League\Csv\Reader;

class TransactionCsvImporter
{
    /**
     * Order matters: the first candidate found in the header wins, so more
     * specific column names (e.g. the actual operation date) are listed
     * before generic ones (e.g. the bank's accounting/settlement date).
     *
     * @var string[]
     */
    private const DATE_COLUMNS = [
        'data operazione', 'data di completamento', 'data contabile',
        'data valuta', 'data di inizio', 'data', 'date', 'transaction_date',
    ];

    /** @var string[] */
    private const DESCRIPTION_COLUMNS = ['descrizione', 'description', 'causale', 'dettaglio', 'nome'];

    /** @var string[] */
    private const AMOUNT_COLUMNS = ['importo', 'amount', 'valore'];

    /** @var string[] */
    private const CATEGORY_COLUMNS = ['categoria', 'category'];

    /** @var string[] */
    private const STATUS_COLUMNS = ['stato', 'state'];

    /** @var string[] */
    private const CANCELLED_STATUS_MARKERS = ['annull', 'cancel', 'rifiut', 'declin', 'fail'];

    /** @var string[] */
    private const DATE_FORMATS = ['Y-m-d H:i:s', 'd/m/Y', 'd-m-Y', 'Y-m-d', 'd/m/y'];

    public function __construct(private readonly TransactionCategorizer $categorizer) {}

    /**
     * Import every valid row of the given CSV file (bank statements often span
     * more than one calendar month, so import isn't restricted to a single
     * month/year - the month/year filter on the card page is a view filter,
     * applied only when displaying transactions afterwards).
     *
     * @return array{imported: int, duplicates: int, skipped: int, error: string|null, latest_year: int|null, latest_month: int|null}
     */
    public function import(FinancialAccount $account, UploadedFile $file, ?int $cardId = null): array
    {
        $csv = Reader::from($file->getRealPath());
        $csv->setDelimiter($this->detectDelimiter($file->getRealPath()));
        $csv->setHeaderOffset(0);

        $originalHeader = $csv->getHeader();
        $headerMap = [];
        foreach ($originalHeader as $column) {
            $headerMap[$this->normalizeHeader($column)] = $column;
        }
        $normalizedHeader = array_keys($headerMap);

        $dateColumn = $this->matchColumn($normalizedHeader, self::DATE_COLUMNS);
        $descriptionColumn = $this->matchColumn($normalizedHeader, self::DESCRIPTION_COLUMNS);
        $amountColumn = $this->matchColumn($normalizedHeader, self::AMOUNT_COLUMNS);
        $categoryColumn = $this->matchColumn($normalizedHeader, self::CATEGORY_COLUMNS);
        $statusColumn = $this->matchColumn($normalizedHeader, self::STATUS_COLUMNS);

        if (! $dateColumn || ! $descriptionColumn || ! $amountColumn) {
            return [
                'imported' => 0,
                'duplicates' => 0,
                'skipped' => 0,
                'error' => 'Non ho riconosciuto le colonne data/descrizione/importo nel file CSV.',
                'latest_year' => null,
                'latest_month' => null,
            ];
        }

        $imported = 0;
        $duplicates = 0;
        $skipped = 0;
        $latestDate = null;

        // Counts how many times an identical (date+amount+description) signature has
        // been seen so far in this file, since real statements can legitimately
        // contain two distinct transactions that look identical (e.g. two top-ups
        // of the same amount on the same day, with no other distinguishing data).
        $occurrenceIndex = [];

        DB::transaction(function () use (
            $csv, $headerMap, $dateColumn, $descriptionColumn, $amountColumn, $categoryColumn, $statusColumn,
            $account, $cardId, &$imported, &$duplicates, &$skipped, &$occurrenceIndex, &$latestDate,
        ) {
            foreach ($csv->getRecords() as $record) {
                $rawDate = $record[$headerMap[$dateColumn]] ?? '';
                $rawDescription = $record[$headerMap[$descriptionColumn]] ?? '';
                $rawAmount = $record[$headerMap[$amountColumn]] ?? '';
                $rawCategory = $categoryColumn ? ($record[$headerMap[$categoryColumn]] ?? '') : '';

                if ($statusColumn && $this->isCancelledStatus($record[$headerMap[$statusColumn]] ?? '')) {
                    $skipped++;

                    continue;
                }

                if (trim($rawDate) === '' || trim($rawDescription) === '' || trim($rawAmount) === '') {
                    $skipped++;

                    continue;
                }

                $date = $this->parseDate($rawDate);
                $amount = $this->parseAmount($rawAmount);

                if (! $date || $amount === null) {
                    $skipped++;

                    continue;
                }

                if (! $latestDate || $date->greaterThan($latestDate)) {
                    $latestDate = $date;
                }

                $direction = $amount < 0 ? 'expense' : 'income';
                $absoluteAmount = abs($amount);
                $description = trim($rawDescription);

                $signature = self::buildSignature($account->id, $date, $direction, $absoluteAmount, $description);

                // If this exact signature repeats within the file, each repetition is
                // treated as its own occurrence: the Nth time we see it here is compared
                // against the Nth time it was ever imported, so genuinely repeated
                // transactions are kept while true re-imports are still recognised as
                // duplicates.
                $occurrence = $occurrenceIndex[$signature] ?? 0;
                $occurrenceIndex[$signature] = $occurrence + 1;

                $dedupHash = self::hashSignature($signature, $occurrence);

                $exists = Transaction::query()
                    ->where('financial_account_id', $account->id)
                    ->where('dedup_hash', $dedupHash)
                    ->exists();

                if ($exists) {
                    $duplicates++;

                    continue;
                }

                $categoryId = null;
                if (trim($rawCategory) !== '') {
                    $categoryId = TransactionCategory::query()
                        ->where(fn ($query) => $query->whereNull('user_id')->orWhere('user_id', $account->user_id))
                        ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($rawCategory))])
                        ->value('id');
                }

                if (! $categoryId) {
                    $categoryId = $this->categorizer->categorize($description, $account->user_id);
                }

                try {
                    Transaction::create([
                        'financial_account_id' => $account->id,
                        'card_id' => $cardId,
                        'transaction_category_id' => $categoryId,
                        'transaction_date' => $date,
                        'description' => $description,
                        'amount' => $absoluteAmount,
                        'direction' => $direction,
                        'dedup_hash' => $dedupHash,
                    ]);

                    $imported++;
                } catch (UniqueConstraintViolationException) {
                    // The (financial_account_id, dedup_hash) unique constraint is the final
                    // safety net: if this exact row was inserted a moment ago by a concurrent
                    // request (e.g. a double-clicked submit), treat it as a duplicate instead
                    // of surfacing a database error.
                    $duplicates++;
                }
            }
        });

        return [
            'imported' => $imported,
            'duplicates' => $duplicates,
            'skipped' => $skipped,
            'error' => null,
            'latest_year' => $latestDate ? (int) $latestDate->format('Y') : null,
            'latest_month' => $latestDate ? (int) $latestDate->format('n') : null,
        ];
    }

    /**
     * Build the identity signature for a transaction: account, calendar day
     * (the `transaction_date` column is a DATE with no time, so hashing a
     * parsed time-of-day here would make the hash impossible to reproduce
     * later from the stored row - e.g. from `finance:dedupe-transactions`),
     * direction, amount and description.
     *
     * Two distinct transactions can share all of this (e.g. two top-ups of
     * the same amount on the same day) - that's what the occurrence index
     * in `import()` and in `finance:dedupe-transactions` is for; it doesn't
     * need time-of-day to tell them apart.
     *
     * Shared with `finance:dedupe-transactions`, which recomputes dedup
     * hashes for already-imported rows using this exact same formula.
     */
    public static function buildSignature(int $accountId, \DateTimeInterface $date, string $direction, float $absoluteAmount, string $description): string
    {
        return implode('|', [
            $accountId,
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

    private function detectDelimiter(string $path): string
    {
        $handle = fopen($path, 'r');
        $firstLine = $handle ? (fgets($handle) ?: '') : '';
        if ($handle) {
            fclose($handle);
        }

        return substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
    }

    private function normalizeHeader(string $header): string
    {
        return Str::of($header)->trim()->lower()->ascii()->toString();
    }

    /**
     * Match a candidate column name against the normalized headers. Uses a
     * "starts with" comparison (not exact) because some banks append extra
     * text to the header, e.g. "Importo ( € )" instead of plain "Importo".
     *
     * @param  string[]  $normalizedHeaders
     * @param  string[]  $candidates
     */
    private function matchColumn(array $normalizedHeaders, array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            foreach ($normalizedHeaders as $header) {
                if ($header === $candidate || str_starts_with($header, $candidate)) {
                    return $header;
                }
            }
        }

        return null;
    }

    private function isCancelledStatus(string $rawStatus): bool
    {
        $normalized = Str::of($rawStatus)->trim()->lower()->ascii()->toString();

        if ($normalized === '') {
            return false;
        }

        foreach (self::CANCELLED_STATUS_MARKERS as $marker) {
            if (str_contains($normalized, $marker)) {
                return true;
            }
        }

        return false;
    }

    private function parseDate(string $raw): ?CarbonImmutable
    {
        $raw = trim($raw);

        foreach (self::DATE_FORMATS as $format) {
            try {
                $date = CarbonImmutable::createFromFormat($format, $raw);
                if ($date !== false) {
                    return $date;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }

    private function parseAmount(string $raw): ?float
    {
        $raw = trim(str_replace(['€', ' '], '', $raw));

        if ($raw === '') {
            return null;
        }

        $hasComma = str_contains($raw, ',');
        $hasDot = str_contains($raw, '.');

        if ($hasComma && $hasDot) {
            $raw = str_replace('.', '', $raw);
            $raw = str_replace(',', '.', $raw);
        } elseif ($hasComma) {
            $raw = str_replace(',', '.', $raw);
        }

        return is_numeric($raw) ? (float) $raw : null;
    }
}
