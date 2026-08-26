<?php

namespace App\Services\BalanceSheet;

use App\Models\BalanceSheetEntry;
use App\Models\BalanceSheetProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Handles the composite "asset purchase" flow: an asset can be created,
 * updated or deleted together with the income, liability (mortgage),
 * expense (installment) and time entries it generates, keeping the
 * user's cash balance in sync with the cash used for the purchase.
 */
class BalanceSheetAssetService
{
    /**
     * @param  array{name: string, category: ?string, asset_value: float, monthly_income: ?float, cash_used: ?float, mortgage_total: ?float, monthly_installment: ?float, hours_per_month: ?float}  $data
     */
    public function create(User $user, array $data): BalanceSheetEntry
    {
        return DB::transaction(function () use ($user, $data) {
            $asset = $user->balanceSheetEntries()->create([
                'type' => 'asset',
                'name' => $data['name'],
                'category' => $data['category'] ?? null,
                'amount' => $data['asset_value'],
                'cash_used' => $data['cash_used'] ?? 0,
                'active' => true,
            ]);

            $this->syncLinkedIncome($asset, (float) ($data['monthly_income'] ?? 0));
            $liability = $this->syncLinkedLiability($asset, (float) ($data['mortgage_total'] ?? 0));
            $this->syncLinkedExpense($asset, (float) ($data['monthly_installment'] ?? 0), $liability);
            $this->syncLinkedTime($asset, (float) ($data['hours_per_month'] ?? 0));

            $cashUsed = (float) ($data['cash_used'] ?? 0);

            if ($cashUsed > 0) {
                BalanceSheetProfile::forUser($user)->decrement('cash_balance', $cashUsed);
            }

            return $asset->fresh();
        });
    }

    /**
     * @param  array{name: string, category: ?string, asset_value: float, monthly_income: ?float, cash_used: ?float, mortgage_total: ?float, monthly_installment: ?float, hours_per_month: ?float}  $data
     */
    public function update(BalanceSheetEntry $asset, array $data): BalanceSheetEntry
    {
        return DB::transaction(function () use ($asset, $data) {
            $user = $asset->user;
            $previousCashUsed = (float) ($asset->cash_used ?? 0);
            $newCashUsed = (float) ($data['cash_used'] ?? 0);

            $asset->fill([
                'name' => $data['name'],
                'category' => $data['category'] ?? null,
                'amount' => $data['asset_value'],
                'cash_used' => $newCashUsed,
            ])->save();

            $this->syncLinkedIncome($asset, (float) ($data['monthly_income'] ?? 0));
            $liability = $this->syncLinkedLiability($asset, (float) ($data['mortgage_total'] ?? 0));
            $this->syncLinkedExpense($asset, (float) ($data['monthly_installment'] ?? 0), $liability);
            $this->syncLinkedTime($asset, (float) ($data['hours_per_month'] ?? 0));

            if ($previousCashUsed !== $newCashUsed) {
                // Liquidità nuova = Liquidità attuale + liquidità precedente - liquidità nuova
                BalanceSheetProfile::forUser($user)->increment('cash_balance', $previousCashUsed - $newCashUsed);
            }

            return $asset->fresh();
        });
    }

    public function delete(BalanceSheetEntry $asset): void
    {
        DB::transaction(function () use ($asset) {
            $cashUsed = (float) ($asset->cash_used ?? 0);

            if ($cashUsed > 0) {
                BalanceSheetProfile::forUser($asset->user)->increment('cash_balance', $cashUsed);
            }

            BalanceSheetEntry::query()->where('linked_asset_id', $asset->id)->delete();
            $asset->delete();
        });
    }

    private function syncLinkedIncome(BalanceSheetEntry $asset, float $amount): void
    {
        $existing = BalanceSheetEntry::query()
            ->where('linked_asset_id', $asset->id)
            ->where('type', 'income')
            ->first();

        if ($amount <= 0) {
            $existing?->delete();

            return;
        }

        $attributes = [
            'name' => "Entrata: {$asset->name}",
            'category' => $asset->category,
            'amount' => $amount,
            'active' => true,
        ];

        if ($existing) {
            $existing->update($attributes);

            return;
        }

        $asset->user->balanceSheetEntries()->create([
            ...$attributes,
            'type' => 'income',
            'frequency' => 'monthly',
            'linked_asset_id' => $asset->id,
        ]);
    }

    private function syncLinkedLiability(BalanceSheetEntry $asset, float $amount): ?BalanceSheetEntry
    {
        $existing = BalanceSheetEntry::query()
            ->where('linked_asset_id', $asset->id)
            ->where('type', 'liability')
            ->first();

        if ($amount <= 0) {
            $existing?->delete();

            return null;
        }

        $attributes = [
            'name' => "Mutuo: {$asset->name}",
            'category' => 'Mutui',
            'amount' => $amount,
            'active' => true,
        ];

        if ($existing) {
            $existing->update($attributes);

            return $existing;
        }

        return $asset->user->balanceSheetEntries()->create([
            ...$attributes,
            'type' => 'liability',
            'linked_asset_id' => $asset->id,
        ]);
    }

    private function syncLinkedExpense(BalanceSheetEntry $asset, float $amount, ?BalanceSheetEntry $liability): void
    {
        $existing = BalanceSheetEntry::query()
            ->where('linked_asset_id', $asset->id)
            ->where('type', 'expense')
            ->first();

        if ($amount <= 0) {
            $existing?->delete();

            return;
        }

        $attributes = [
            'name' => "Rata: {$asset->name}",
            'category' => 'Mutui',
            'amount' => $amount,
            'active' => true,
            'linked_liability_id' => $liability?->id,
        ];

        if ($existing) {
            $existing->update($attributes);

            return;
        }

        $asset->user->balanceSheetEntries()->create([
            ...$attributes,
            'type' => 'expense',
            'frequency' => 'monthly',
            'linked_asset_id' => $asset->id,
        ]);
    }

    private function syncLinkedTime(BalanceSheetEntry $asset, float $hours): void
    {
        $existing = BalanceSheetEntry::query()
            ->where('linked_asset_id', $asset->id)
            ->where('type', 'time')
            ->first();

        if ($hours <= 0) {
            $existing?->delete();

            return;
        }

        $attributes = [
            'name' => "Tempo: {$asset->name}",
            'category' => $asset->name,
            'hours_per_month' => $hours,
            'active' => true,
        ];

        if ($existing) {
            $existing->update($attributes);

            return;
        }

        $asset->user->balanceSheetEntries()->create([
            ...$attributes,
            'type' => 'time',
            'time_kind' => 'consumes',
            'linked_asset_id' => $asset->id,
        ]);
    }
}
