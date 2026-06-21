<?php

namespace App\Application\Snapshots;

use App\Application\FX\FxService;
use App\Application\Valuation\InvestmentValuation;
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
    public function __construct(
        private readonly InvestmentValuation $valuation,
        private readonly FxService $fxService,
    ) {}
    public function takeDailySnapshot(string $userId, ?string $date = null): PortfolioSnapshot
    {
        $date = $date ?? now()->toDateString();
        $user = User::findOrFail($userId);

        $investments = Investment::forUser($userId)
            ->where('status', 'active')
            ->with(['assetType', 'latestPrice.provider'])
            ->get();

        return DB::transaction(function () use ($userId, $date, $user, $investments) {
            $totalValue = 0.0;
            $totalCost = 0.0;
            $fxRates = [];

            // Per-investment snapshots (idempotent upsert).
            foreach ($investments as $investment) {
                $currentValue = $this->currentValue($investment);
                $cost = $investment->purchase_price !== null
                    ? (float) $investment->quantity * (float) $investment->purchase_price
                    : 0.0;

                // Convert to base currency for portfolio totals.
                $valueConv = $this->fxService->convert($currentValue, $investment->currency, $user->base_currency);
                $costConv = $this->fxService->convert($cost, $investment->purchase_currency ?? $investment->currency, $user->base_currency);

                $totalValue += $valueConv['rate'] !== null ? $valueConv['amount'] : $currentValue;
                $totalCost += $costConv['rate'] !== null ? $costConv['amount'] : $cost;

                if ($valueConv['rate'] !== null && ! isset($fxRates[$valueConv['source'] ?? ''])) {
                    $fxRates[$valueConv['source'] ?? ''] = $valueConv;
                }

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

            $fx = reset($fxRates);

            $portfolio = PortfolioSnapshot::firstOrNew([
                'user_id' => $userId,
                'snapshot_date' => $date,
            ]);
            $portfolio->fill([
                'total_value' => round($totalValue, 2),
                'total_cost' => round($totalCost, 2),
                'currency' => $user->base_currency,
                'fx_rate' => $fx ? $fx['rate'] : null,
                'fx_source' => $fx ? $fx['source'] : null,
                'fx_from_currency' => $fx ? $fx['from_currency'] : null,
                'active_count' => $investments->count(),
            ])->save();

            return $portfolio;
        });
    }

    private function currentValue(Investment $i): float
    {
        $marketPrice = $i->latestPrice?->first()?->price;

        return $this->valuation->currentValue(
            $i,
            $marketPrice !== null ? (float) $marketPrice : null,
        );
    }
}
