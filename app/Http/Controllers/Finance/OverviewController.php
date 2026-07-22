<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OverviewController extends Controller
{
    /**
     * Show income/expense totals for every month, grouped by year, across
     * all of the user's financial accounts.
     */
    public function index(Request $request): Response
    {
        $accountIds = $request->user()->financialAccounts()->pluck('id');

        $transactions = Transaction::query()
            ->whereIn('financial_account_id', $accountIds)
            ->get(['transaction_date', 'amount', 'direction']);

        $byYear = [];
        foreach ($transactions as $transaction) {
            $year = (int) $transaction->transaction_date->format('Y');
            $month = (int) $transaction->transaction_date->format('n');
            $amount = (float) $transaction->amount;

            $byYear[$year][$month]['income'] = ($byYear[$year][$month]['income'] ?? 0)
                + ($transaction->direction === 'income' ? $amount : 0);
            $byYear[$year][$month]['expense'] = ($byYear[$year][$month]['expense'] ?? 0)
                + ($transaction->direction === 'expense' ? $amount : 0);
        }

        $years = empty($byYear) ? [now()->year] : collect(array_keys($byYear))->sortDesc()->values()->all();

        $overview = collect($years)->map(function (int $year) use ($byYear) {
            $months = collect(range(1, 12))->map(function (int $month) use ($byYear, $year) {
                $data = $byYear[$year][$month] ?? ['income' => 0, 'expense' => 0];

                return [
                    'month' => $month,
                    'income' => round($data['income'], 2),
                    'expense' => round($data['expense'], 2),
                ];
            })->values();

            return [
                'year' => $year,
                'months' => $months,
                'totals' => [
                    'income' => round($months->sum('income'), 2),
                    'expense' => round($months->sum('expense'), 2),
                ],
            ];
        })->values();

        return Inertia::render('finance/Overview/Index', [
            'overview' => $overview,
        ]);
    }
}
