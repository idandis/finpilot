<?php

namespace App\Services\Finance;

use App\Models\Card;
use App\Models\TransactionCategory;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
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

    public function __construct(private readonly TransactionRowPersister $persister) {}

    /**
     * Import every valid row of the given CSV file (bank statements often span
     * more than one calendar month, so import isn't restricted to a single
     * month/year - the month/year filter on the card page is a view filter,
     * applied only when displaying transactions afterwards).
     *
     * @return array{imported: int, duplicates: int, skipped: int, error: string|null, latest_year: int|null, latest_month: int|null}
     */
    public function import(Card $card, UploadedFile $file): array
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

        $skipped = 0;
        $rows = [];

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

            $categoryId = null;
            if (trim($rawCategory) !== '') {
                $categoryId = TransactionCategory::query()
                    ->where(fn ($query) => $query->whereNull('user_id')->orWhere('user_id', $card->user_id))
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($rawCategory))])
                    ->value('id');
            }

            $rows[] = [
                'date' => $date,
                'description' => trim($rawDescription),
                'amount' => abs($amount),
                'direction' => $amount < 0 ? 'expense' : 'income',
                'category_id' => $categoryId,
            ];
        }

        $result = $this->persister->persist($card, $rows);

        return [
            ...$result,
            'skipped' => $skipped,
            'error' => null,
        ];
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
