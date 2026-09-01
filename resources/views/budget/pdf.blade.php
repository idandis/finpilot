@php
    // Migliaia separate da uno spazio stretto, come nell'app: 2 000,00.
    $money = fn (float $value) => number_format($value, 2, ',', "\u{202F}");
    $income = $sections[0];
    $expense = $sections[1];
    $balance = $income['planned'] - $expense['planned'];
@endphp
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <title>Budget {{ $monthLabel }}</title>
    <style>
        @page { margin: 28px 32px; }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10px;
            color: #1f2937;
        }

        h1 {
            font-size: 17px;
            margin: 0;
        }
        .subtitle {
            color: #6b7280;
            font-size: 10px;
            margin: 2px 0 0;
        }
        .masthead {
            border-bottom: 2px solid #1f2937;
            padding-bottom: 8px;
            margin-bottom: 14px;
        }

        /* Le due colonne del bilancio: entrate a sinistra, uscite a destra. */
        table.ledger { width: 100%; border-collapse: separate; border-spacing: 10px 0; }
        td.col { width: 50%; vertical-align: top; }

        table.section-head {
            width: 100%;
            border-collapse: collapse;
        }
        .section-head {
            color: #ffffff;
            padding: 6px 8px;
            font-size: 11px;
            font-weight: bold;
        }
        .section-head.income { background-color: #059669; }
        .section-head.expense { background-color: #dc2626; }
        .section-head td { color: #ffffff; padding: 0; }
        .section-head .amount { text-align: right; }

        table.rows { width: 100%; border-collapse: collapse; }

        .category td {
            font-weight: bold;
            background-color: #f3f4f6;
            padding: 4px 6px;
            border-bottom: 1px solid #e5e7eb;
        }
        .category td.name { border-left: 4px solid #9ca3af; }

        .subcategory td {
            padding: 3px 6px 3px 16px;
            color: #374151;
            border-bottom: 1px solid #f3f4f6;
        }

        td.amount {
            text-align: right;
            white-space: nowrap;
            width: 74px;
        }

        .category-block { page-break-inside: avoid; }

        .section-total td {
            font-weight: bold;
            border-top: 2px solid #d1d5db;
            padding: 5px 6px;
        }

        .empty { color: #9ca3af; font-style: italic; }

        table.summary {
            width: 100%;
            border-collapse: collapse;
            margin-top: 18px;
        }
        table.summary td {
            padding: 5px 8px;
            border-bottom: 1px solid #e5e7eb;
        }
        table.summary td.amount { width: 110px; font-weight: bold; }
        .summary .income-total { color: #059669; }
        .summary .expense-total { color: #dc2626; }
        .summary tr.balance td {
            border-top: 2px solid #1f2937;
            border-bottom: none;
            font-size: 12px;
            font-weight: bold;
            padding-top: 7px;
        }
        .positive { color: #059669; }
        .negative { color: #dc2626; }

        .footnote {
            margin-top: 16px;
            color: #9ca3af;
            font-size: 8px;
        }
    </style>
</head>
<body>
    <div class="masthead">
        <h1>Budget mensile</h1>
        <p class="subtitle">{{ ucfirst($monthLabel) }} · importi attesi in EUR</p>
    </div>

    <table class="ledger">
        <tr>
            @foreach ([$income, $expense] as $section)
                @php $isIncome = $loop->first; @endphp
                <td class="col">
                    <table class="section-head {{ $isIncome ? 'income' : 'expense' }}">
                        <tr>
                            <td>{{ strtoupper($section['title']) }}</td>
                            <td class="amount">{{ $money($section['planned']) }}</td>
                        </tr>
                    </table>

                    @if (count($section['categories']) === 0)
                        <p class="empty">Nessuna categoria.</p>
                    @else
                        @foreach ($section['categories'] as $category)
                            <table class="rows category-block">
                                <tr class="category">
                                    <td class="name" style="border-left-color: {{ $category['color'] }}">
                                        {{ $category['name'] }}
                                    </td>
                                    <td class="amount">{{ $money($category['planned']) }}</td>
                                </tr>
                                @forelse ($category['subcategories'] as $subcategory)
                                    <tr class="subcategory">
                                        <td>{{ $subcategory['name'] }}</td>
                                        <td class="amount">{{ $money($subcategory['planned']) }}</td>
                                    </tr>
                                @empty
                                    <tr class="subcategory">
                                        <td class="empty">Nessuna voce.</td>
                                        <td class="amount"></td>
                                    </tr>
                                @endforelse
                            </table>
                        @endforeach

                        <table class="rows">
                            <tr class="section-total">
                                <td>Totale {{ strtolower($section['title']) }}</td>
                                <td class="amount">{{ $money($section['planned']) }}</td>
                            </tr>
                        </table>
                    @endif
                </td>
            @endforeach
        </tr>
    </table>

    <table class="summary">
        <tr>
            <td>Totale entrate attese</td>
            <td class="amount income-total">{{ $money($income['planned']) }}</td>
        </tr>
        <tr>
            <td>Totale uscite attese</td>
            <td class="amount expense-total">− {{ $money($expense['planned']) }}</td>
        </tr>
        <tr class="balance">
            <td>Saldo atteso del mese</td>
            <td class="amount {{ $balance >= 0 ? 'positive' : 'negative' }}">{{ $money($balance) }}</td>
        </tr>
    </table>

    <p class="footnote">Generato il {{ $generatedAt }} · ManageMe</p>
</body>
</html>
