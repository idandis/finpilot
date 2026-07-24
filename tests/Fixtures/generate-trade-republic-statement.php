<?php

/**
 * Standalone generator for the Trade Republic PDF fixtures used by
 * TransactionPdfImportTest. Not part of the test suite itself - run manually
 * whenever the fixtures need to be regenerated or extended:
 *
 *   php tests/Fixtures/generate-trade-republic-statement.php extended > tests/Fixtures/trade-republic-statement-extended.pdf
 *   php tests/Fixtures/generate-trade-republic-statement.php unreconciled > tests/Fixtures/trade-republic-statement-unreconciled.pdf
 *
 * Builds a minimal, hand-rolled, uncompressed PDF (no external PDF library
 * dependency) with one text content stream, mirroring the structure Trade
 * Republic's own statements use: a summary block containing "SALDO
 * INIZIALE", a "TRANSAZIONI SUL CONTO" table, and a trailing "PANORAMICA DEL
 * SALDO" marker - the same three anchors TransactionPdfImporter looks for.
 * Amounts/balances are computed programmatically from a running balance, so
 * every row reconciles exactly (unless a scenario deliberately breaks one).
 */

/** Euro-formatted amount, e.g. 1234.5 -> "1.234,50 €" (0x80 = € under WinAnsiEncoding). */
function euro(float $amount): string
{
    return number_format($amount, 2, ',', '.')." \x80";
}

/** Escape a string for use inside a PDF literal string "(...)". */
function pdfEscape(string $text): string
{
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
}

/**
 * @param  array<int, string>  $lines
 */
function buildPdf(array $lines): string
{
    $content = "BT\n/F1 9 Tf\n50 760 Td\n12 TL\n";
    foreach ($lines as $line) {
        $content .= '('.pdfEscape($line).") Tj\nT*\n";
    }
    $content .= "ET\n";

    $objects = [];
    $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
    $objects[2] = '<< /Type /Pages /Kids [3 0 R] /Count 1 >>';
    $objects[3] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>';
    $objects[4] = '<< /Length '.strlen($content)." >>\nstream\n{$content}endstream";
    $objects[5] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';

    $pdf = "%PDF-1.4\n";
    $offsets = [];

    foreach ($objects as $number => $body) {
        $offsets[$number] = strlen($pdf);
        $pdf .= "{$number} 0 obj\n{$body}\nendobj\n";
    }

    $xrefOffset = strlen($pdf);
    $count = count($objects) + 1;

    $pdf .= "xref\n0 {$count}\n";
    $pdf .= "0000000000 65535 f \n";
    foreach ($offsets as $offset) {
        $pdf .= sprintf("%010d 00000 n \n", $offset);
    }

    $pdf .= "trailer\n<< /Size {$count} /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
}

/**
 * Tracks a running balance and appends one already-reconciled transaction
 * row per call, in Trade Republic's own text layout: date, tipo,
 * description, then the row amount and the balance printed after it.
 */
final class StatementBuilder
{
    /** @var array<int, string> */
    public array $lines = [];

    public function __construct(private float $balance) {}

    public function summaryHeader(): void
    {
        $this->lines[] = 'TRADE REPUBLIC';
        $this->lines[] = 'TRADE REPUBLIC BANK GMBH, BRANCH ITALY';
        $this->lines[] = '';
        $this->lines[] = 'ESTRATTO CONTO RIASSUNTIVO';
        $this->lines[] = '';
        $this->lines[] = 'PRODOTTO SALDO INIZIALE IN ENTRATA IN USCITA SALDO FINALE';
        $this->lines[] = 'Conto corrente '.euro($this->balance);
        $this->lines[] = '';
        $this->lines[] = 'TRANSAZIONI SUL CONTO';
        $this->lines[] = '';
        $this->lines[] = 'DATA TIPO DESCRIZIONE IN ENTRATA IN USCITA SALDO';
    }

    public function row(string $date, string $tipo, string $description, float $delta): void
    {
        $this->balance += $delta;
        $this->lines[] = "{$date} {$tipo} {$description} ".euro(abs($delta)).' '.euro($this->balance);
    }

    /** Prints a row whose balance is deliberately wrong by the given offset - never applied to the running balance. */
    public function unreconciledRow(string $date, string $tipo, string $description, float $delta, float $printedOffset): void
    {
        $wrongBalance = $this->balance + $delta + $printedOffset;
        $this->lines[] = "{$date} {$tipo} {$description} ".euro(abs($delta)).' '.euro($wrongBalance);
        // The running balance intentionally does NOT absorb this row - the
        // importer itself re-syncs to whatever the document prints next.
        $this->balance = $wrongBalance;
    }

    public function footer(): void
    {
        $this->lines[] = '';
        $this->lines[] = 'PANORAMICA DEL SALDO';
    }
}

function scenarioExtended(): array
{
    $s = new StatementBuilder(10000.00);
    $s->summaryHeader();

    $s->row('02 mag 2025', 'Bonifico', 'Incoming transfer from CAMBIAGHI FRANCESCA (IT20J36772223000EM000911706)', 500.00);
    $s->row('05 mag 2025', 'Bonifico', 'Outgoing transfer to MISSIAGLIA ALESSANDRO ALDO (IT60X0542811101000000123456)', -300.00);
    $s->row('10 mag 2025', 'Commercio', 'Buy trade IE00BK5BQT80 Vanguard Funds PLC - Vanguard FTSE All-World UCITS ETF (USD) Accumulating, quantity: 1.5', -200.00);
    $s->row('12 mag 2025', 'Commercio', 'Buy trade US84615Q1031 SPACE EXPL.TECHS. CL.A, quantity: 1.078399', -500.00);
    $s->row('13 mag 2025', 'Commercio', 'Cancellation Buy trade US84615Q1031 SPACE EXPL.TECHS. CL.A, quantity: 1.078399', 500.00);
    $s->row('14 mag 2025', 'Commercio', 'Sell trade IE00BK5BQT80 Vanguard Funds PLC - Vanguard FTSE All-World UCITS ETF (USD) Accumulating, quantity: 0.5', 80.00);
    $s->row('20 mag 2025', 'Imposte', 'Tax Optimisation', 0.50);
    $s->row('22 mag 2025', 'Imposte', 'Tax Optimisation', -0.30);
    $s->row('25 mag 2025', 'Imposte', 'Stamp Duty Tax (Portfolio)', -0.10);
    $s->row('26 mag 2025', 'Imposte', 'Stamp Duty Tax (Cash)', -0.05);
    $s->row('27 mag 2025', 'Imposte', 'Cancellation Stamp Duty Tax (Portfolio)', 0.10);
    $s->row('01 giu 2025', 'Interessi', 'Your interest payment', 1.20);
    $s->row('01 giu 2025', 'Premio', 'Your Saveback payment', 2.00);
    $s->row('02 giu 2025', 'Commercio', 'Savings plan execution IE00BK5BQT80 Vanguard Funds PLC - Vanguard FTSE All-World UCITS ETF (USD) Accumulating, quantity: 0.3', -50.00);
    $s->row('03 giu 2025', 'Premio', 'Cash reward allocation', 5.00);
    $s->row('10 giu 2025', 'Rendimento', 'Cash Dividend for ISIN XF000ETH0019', 3.50);
    $s->row('20 giu 2025', 'Transazione con carta', '8378519 WWW.TESTSHOP.IT', -25.00);
    $s->row('21 giu 2025', 'Transazione con carta', 'RYANAIR S1KFXN0', -50.50);
    $s->row('01 lug 2025', 'Interessi', 'Interest payment', 1.25);

    $s->footer();

    return $s->lines;
}

function scenarioUnreconciled(): array
{
    $s = new StatementBuilder(10000.00);
    $s->summaryHeader();

    $s->row('02 mag 2025', 'Bonifico', 'Incoming transfer from CAMBIAGHI FRANCESCA (IT20J36772223000EM000911706)', 500.00);
    // Deliberately printed 0.01 off from what the amount+previous balance
    // would reconcile to - the importer must skip only this row and keep
    // going, reporting it in skipped_rows.
    $s->unreconciledRow('05 mag 2025', 'Bonifico', 'Outgoing transfer to MISSIAGLIA ALESSANDRO ALDO (IT60X0542811101000000123456)', -300.00, 0.01);
    $s->row('10 mag 2025', 'Commercio', 'Buy trade IE00BK5BQT80 Vanguard Funds PLC - Vanguard FTSE All-World UCITS ETF (USD) Accumulating, quantity: 1.5', -200.00);

    $s->footer();

    return $s->lines;
}

$scenario = $argv[1] ?? null;

$lines = match ($scenario) {
    'extended' => scenarioExtended(),
    'unreconciled' => scenarioUnreconciled(),
    default => null,
};

if ($lines === null) {
    fwrite(STDERR, "Usage: php generate-trade-republic-statement.php <extended|unreconciled> > output.pdf\n");
    exit(1);
}

echo buildPdf($lines);
