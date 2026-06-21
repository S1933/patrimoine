<?php

namespace App\Application\Snapshots;

use App\Models\Investment;
use App\Models\InvestmentSnapshot;
use App\Models\PortfolioSnapshot;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Takes a daily snapshot of the portfolio: one portfolio_snapshots row +
 * one investment_snapshots row per active investment.
 * Idempotent: re-running for the same date upserts.
 */
final class SnapshotService
{
    public function takeDailySnapshot(string $userId, ?string $date = null): PortfolioSnapshot
    {
        $date = $date ?? now()->toDateString();
        $user = User::findOrFail($userId);

        $investments = Investment::forUser($userId)
            ->where('status', 'active')
            ->with(['assetType', 'latestPrice.provider'])
            ->get();

        return DB::transaction(function () use ($userId, $date, $user, $investments) {
            // Per-investment snapshots (idempotent upsert).
            foreach ($investments as $investment) {
                $currentValue = $this->currentValue($investment);
                $cost = $investment->purchase_price !== null
                    ? (float) $investment->quantity * (float) $investment->purchase_price
                    : 0.0;

                $snap = InvestmentSnapshot::firstOrNew([
                    'investment_id' => $investment->id,
                    'snapshot_date' => $date,
                ]);
                $snap->fill([
                    'user_id' => $userId,
                    'quantity' => (float) $investment->quantity,
                    'price' => $currentValue > 0 && (float) $investment->quantity > 0
                        ? $currentValue / (float) $investment->quantity
                        : 0,
                    'value' => $currentValue,
                    'cost' => $cost,
                    'currency' => $investment->currency,
                ])->save();
            }

            $totalValue = $investments->sum(fn (Investment $i) => $this->currentValue($i));
            $totalCost = $investments->sum(function (Investment $i) {
                return $i->purchase_price !== null
                    ? (float) $i->quantity * (float) $i->purchase_price
                    : 0.0;
            });

            $portfolio = PortfolioSnapshot::firstOrNew([
                'user_id' => $userId,
                'snapshot_date' => $date,
            ]);
            $portfolio->fill([
                'total_value' => round($totalValue, 2),
                'total_cost' => round($totalCost, 2),
                'currency' => $user->base_currency,
                'active_count' => $investments->count(),
            ])->save();

            return $portfolio;
        });
    }

    private function currentValue(Investment $i): float
    {
        if ($i->manual_value !== null) {
            return (float) $i->manual_value;
        }

        $latest = $i->latestPrice?->first();
        if ($latest) {
            return (float) $i->quantity * (float) $latest->price;
        }

        return 0.0;
    }
}
