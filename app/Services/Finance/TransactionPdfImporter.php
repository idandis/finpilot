<?php

namespace App\Services\Finance;

use App\Models\Card;
use App\Models\TransactionCategory;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Smalot\PdfParser\Parser;

class TransactionPdfImporter
{
    /** @var array<string, int> */
    private const MONTHS = [
        'gen' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'mag' => 5, 'giu' => 6,
        'lug' => 7, 'ago' => 8, 'set' => 9, 'ott' => 10, 'nov' => 11, 'dic' => 12,
    ];

    /**
     * The row "tipo" values used by Trade Republic's own statement template.
     * "Transazione con carta" is the only one made of more than one word.
     */
    private const TIPI = 'Bonifico|Commercio|Interessi|Imposte|Premio|Rendimento|Transazione\s+con\s+carta';

    /**
     * These operation types are all portfolio activity (trades, savings
     * plans, interest, dividends, cashback, portfolio tax), as opposed to
     * money moving in/out of the cash account (Bonifico) or card purchases
     * (Transazione con carta) - so they're categorized as "Investimenti"
     * directly from the row type, rather than left for keyword rules that
     * would never match an ISIN or a stock name.
     */
    private const INVESTMENT_TIPI = ['Commercio', 'Interessi', 'Rendimento', 'Premio', 'Imposte'];

    /**
     * Matches Trade Republic's own "Commercio" description format, e.g.
     * "Buy trade IE00BK5BQT80 Vanguard Funds PLC - Vanguard FTSE All-World
     * UCITS ETF (USD) Accumulating, quantity: 0.344482" - captures the ISIN
     * and the traded quantity, ignored for every other row type.
     */
    private const TRADE_PATTERN = '/^(?:Cancellation\s+)?(?:Buy trade|Sell trade|Savings plan execution)\s+([A-Z]{2}[A-Z0-9]{10})\s+.+?,\s*quantity:\s*([\d.]+)$/';

    public function __construct(private readonly TransactionRowPersister $persister) {}

    /**
     * Import a Trade Republic "estratto conto" PDF. Unlike the CSV column
     * layout, this bank's statement doesn't label each row as income or
     * expense - only the running balance printed after every row does. So
     * direction is derived by comparing each row's amount against how much
     * the balance actually moved, starting from the statement's own
     * "saldo iniziale".
     *
     * @return array{imported: int, duplicates: int, skipped: int, error: string|null, latest_year: int|null, latest_month: int|null}
     */
    public function import(Card $card, UploadedFile $file): array
    {
        $text = (new Parser)->parseFile($file->getRealPath())->getText();

        $tableStart = mb_strpos($text, 'TRANSAZIONI SUL CONTO');

        if (! str_contains($text, 'TRADE REPUBLIC') || $tableStart === false) {
            return $this->error('Questo file non sembra un estratto conto Trade Republic.');
        }

        $panoramicaPosition = mb_strpos($text, 'PANORAMICA DEL SALDO');
        $tableEnd = $panoramicaPosition !== false ? $panoramicaPosition : mb_strlen($text);
        $table = mb_substr($text, $tableStart, $tableEnd - $tableStart);
        $summary = mb_substr($text, 0, $tableStart);

        $startingBalance = $this->parseStartingBalance($summary);

        if ($startingBalance === null) {
            return $this->error('Non ho riconosciuto il saldo iniziale in questo estratto conto.');
        }

        $pattern = '/(\d{1,2})\s+(gen|feb|mar|apr|mag|giu|lug|ago|set|ott|nov|dic)\s+(\d{4})\s+('.self::TIPI.')\s*(.*?)\s+(-?[\d.]+,\d{2})\s*€\s*(-?[\d.]+,\d{2})\s*€/su';
        preg_match_all($pattern, $table, $matches, PREG_SET_ORDER);

        if (empty($matches)) {
            return $this->error('Non ho trovato transazioni in questo estratto conto.');
        }

        $investmentCategoryId = TransactionCategory::query()
            ->whereNull('user_id')
            ->where('name', 'Investimenti')
            ->value('id');

        $rows = [];
        $skipped = 0;
        $balance = $startingBalance;

        foreach ($matches as $match) {
            [, $day, $month, $year, $tipoRaw, $descriptionRaw, $amountRaw, $saldoRaw] = $match;

            $tipo = trim(preg_replace('/\s+/u', ' ', $tipoRaw));
            $amount = $this->parseAmount($amountRaw);
            $saldo = $this->parseAmount($saldoRaw);

            $amountCents = (int) round($amount * 100);
            $saldoCents = (int) round($saldo * 100);
            $balanceCents = (int) round($balance * 100);

            if ($saldoCents - $balanceCents === $amountCents) {
                $direction = 'income';
            } elseif ($balanceCents - $saldoCents === $amountCents) {
                $direction = 'expense';
            } else {
                // The row's amount doesn't reconcile against the running balance
                // printed by the bank - trust the document's own balance for the
                // next row rather than guessing this one's direction.
                $balance = $saldo;
                $skipped++;

                continue;
            }

            $balance = $saldo;
            $description = trim(preg_replace('/\s+/u', ' ', $descriptionRaw));
            $trade = $this->parseTrade($description);

            $rows[] = [
                'date' => CarbonImmutable::createFromDate((int) $year, self::MONTHS[$month], (int) $day),
                'description' => $description,
                'isin' => $trade['isin'],
                'quantity' => $trade['quantity'],
                'amount' => $amount,
                'direction' => $direction,
                'category_id' => in_array($tipo, self::INVESTMENT_TIPI, true) ? $investmentCategoryId : null,
            ];
        }

        $result = $this->persister->persist($card, $rows);

        return [
            ...$result,
            'skipped' => $skipped,
            'error' => null,
        ];
    }

    /**
     * @return array{imported: int, duplicates: int, skipped: int, error: string, latest_year: null, latest_month: null}
     */
    private function error(string $message): array
    {
        return [
            'imported' => 0,
            'duplicates' => 0,
            'skipped' => 0,
            'error' => $message,
            'latest_year' => null,
            'latest_month' => null,
        ];
    }

    private function parseStartingBalance(string $summary): ?float
    {
        $matched = preg_match(
            '/PRODOTTO\s+SALDO INIZIALE\s+IN ENTRATA\s+IN USCITA\s+SALDO FINALE\s+.*?(-?[\d.]+,\d{2})\s*€/su',
            $summary,
            $match,
        );

        return $matched ? $this->parseAmount($match[1]) : null;
    }

    private function parseAmount(string $raw): float
    {
        return (float) str_replace(['.', ','], ['', '.'], trim($raw));
    }

    /**
     * @return array{isin: string|null, quantity: float|null}
     */
    private function parseTrade(string $description): array
    {
        if (! preg_match(self::TRADE_PATTERN, $description, $match)) {
            return ['isin' => null, 'quantity' => null];
        }

        return ['isin' => $match[1], 'quantity' => (float) $match[2]];
    }
}
