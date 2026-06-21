<?php

namespace App\Application\Dashboard;

use App\Application\FX\FxService;
use App\Application\Valuation\InvestmentValuation;
use App\Models\Investment;
use App\Models\InvestmentStrategyAllocation;
use App\Models\PortfolioSnapshot;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

/**
 * Computes all dashboard aggregates for a user.
 * Pure read-model — no side effects, safe to call repeatedly.
 */
final class DashboardCalculator
{
    public function __construct(
        private readonly InvestmentValuation $valuation,
        private readonly FxService $fxService,
    ) {}

    /**
     * @return array{
     *     total_value: float,
     *     total_cost: float,
     *     pnl_absolute: float,
     *     pnl_percent: float|null,
     *     active_count: int,
     *     last_updated_at: string|null,
     *     currency: string
     * }
     */
    public function summary(string $userId, string $currency = 'EUR'): array
    {
        $investments = $this->loadActiveInvestments($userId);
        $rows = $investments->map(fn (Investment $i) => $this->normalizeRow($this->computeRow($i), $i, $currency));

        $totalValue = $rows->sum('current_value') ?? 0.0;
        $totalCost = $rows->sum('purchase_value') ?? 0.0;
        $pnlAbsolute = $totalValue - $totalCost;
        $pnlPercent = $totalCost > 0 ? ($pnlAbsolute / $totalCost) * 100 : null;

        $lastUpdated = $investments
            ->map(fn (Investment $i) => $i->latestPrice?->first()?->fetched_at)
            ->filter()
            ->max();

        return [
            'total_value' => round($totalValue, 2),
            'total_cost' => round($totalCost, 2),
            'pnl_absolute' => round($pnlAbsolute, 2),
            'pnl_percent' => $pnlPercent !== null ? round($pnlPercent, 2) : null,
            'active_count' => $investments->count(),
            'last_updated_at' => $lastUpdated?->toIso8601String(),
            'currency' => $currency,
        ];
    }

    /**
     * Allocation by asset class.
     *
     * @return list<array{
     *     code: string, label: string, value: float, percent: float, count: int,
     *     target_percent: float|null, deviation_points: float|null
     * }>
     */
    public function allocation(string $userId, string $baseCurrency = 'EUR'): array
    {
        $investments = $this->loadActiveInvestments($userId);
        $totalValue = $investments->map(fn (Investment $i) => $this->valueInCurrency($i, $baseCurrency))->sum();
        $actual = $investments
            ->groupBy(fn (Investment $i) => $i->assetType->code)
            ->map(function (Collection $group) use ($totalValue, $baseCurrency) {
                $value = $group->sum(fn (Investment $i) => $this->valueInCurrency($i, $baseCurrency));
                $first = $group->first();

                return [
                    'code' => $first->assetType->code,
                    'label' => $first->assetType->label,
                    'value' => round($value, 2),
                    'percent' => $totalValue > 0 ? round($value / $totalValue * 100, 2) : 0.0,
                    'count' => $group->count(),
                ];
            })
            ->keyBy('code');

        $targets = InvestmentStrategyAllocation::query()
            ->with('assetType')
            ->where('user_id', $userId)
            ->get();
        $hasStrategy = $targets->isNotEmpty();

        foreach ($targets as $target) {
            $code = $target->assetType->code;
            if (! $actual->has($code)) {
                $actual->put($code, [
                    'code' => $code,
                    'label' => $target->assetType->label,
                    'value' => 0.0,
                    'percent' => 0.0,
                    'count' => 0,
                ]);
            }
        }

        $targetsByCode = $targets->keyBy(fn (InvestmentStrategyAllocation $target) => $target->assetType->code);

        return $actual
            ->map(function (array $item) use ($hasStrategy, $targetsByCode) {
                if (! $hasStrategy) {
                    return [
                        ...$item,
                        'target_percent' => null,
                        'deviation_points' => null,
                    ];
                }

                $targetPercent = (float) ($targetsByCode->get($item['code'])?->target_percent ?? 0);

                return [
                    ...$item,
                    'target_percent' => $targetPercent,
                    'deviation_points' => round($item['percent'] - $targetPercent, 2),
                ];
            })
            ->sortByDesc('value')
            ->values()
            ->all();
    }

    /**
     * Per-investment breakdown.
     *
     * @return list<array{
     *     id: string, name: string, asset_type_code: string, asset_type_label: string,
     *     current_value: float, purchase_value: float|null, pnl_absolute: float|null,
     *     pnl_percent: float|null, weight: float, status: string
     * }>
     */
    public function breakdown(string $userId, string $baseCurrency = 'EUR'): array
    {
        $investments = $this->loadActiveInvestments($userId);
        $totalValue = $investments->map(fn (Investment $i) => $this->valueInCurrency($i, $baseCurrency))->sum();

        return $investments
            ->map(function (Investment $i) use ($totalValue, $baseCurrency) {
                $row = $this->normalizeRow($this->computeRow($i), $i, $baseCurrency);

                return [
                    'id' => $i->id,
                    'name' => $i->name,
                    'asset_type_code' => $i->assetType->code,
                    'asset_type_label' => $i->assetType->label,
                    'current_value' => round($row['current_value'], 2),
                    'purchase_value' => $row['purchase_value'] !== null ? round($row['purchase_value'], 2) : null,
                    'pnl_absolute' => $row['pnl_absolute'] !== null ? round($row['pnl_absolute'], 2) : null,
                    'pnl_percent' => $row['pnl_percent'] !== null ? round($row['pnl_percent'], 2) : null,
                    'weight' => $totalValue > 0 ? round($row['current_value'] / $totalValue * 100, 2) : 0.0,
                    'status' => $i->status,
                ];
            })
            ->sortByDesc('current_value')
            ->values()
            ->all();
    }

    /**
     * Allocation by country (from country_allocations on each investment).
     *
     * @return list<array{country_code: string, value: float, percent: float, count: int}>
     */
    public function countryAllocation(string $userId, string $baseCurrency = 'EUR'): array
    {
        $investments = $this->loadActiveInvestments($userId);
        $totalValue = $investments->map(fn (Investment $i) => $this->valueInCurrency($i, $baseCurrency))->sum();
        $countryTotals = [];
        $countryCounts = [];

        foreach ($investments as $i) {
            $value = $this->valueInCurrency($i, $baseCurrency);
            $allocations = $i->country_allocations;

            if (! empty($allocations) && is_array($allocations)) {
                foreach ($allocations as $alloc) {
                    $country = strtoupper(trim($alloc['country'] ?? ''));
                    $percent = (float) ($alloc['percent'] ?? 0);
                    if ($country !== '' && $percent > 0) {
                        $countryTotals[$country] = ($countryTotals[$country] ?? 0) + ($value * $percent / 100);
                        $countryCounts[$country] = ($countryCounts[$country] ?? 0) + 1;
                    }
                }
            }
        }

        $result = [];
        foreach ($countryTotals as $code => $val) {
            $result[] = [
                'country_code' => $code,
                'value' => round($val, 2),
                'percent' => $totalValue > 0 ? round($val / $totalValue * 100, 2) : 0.0,
                'count' => $countryCounts[$code] ?? 1,
            ];
        }

        usort($result, fn ($a, $b) => $b['value'] <=> $a['value']);

        return $result;
    }

    /**
     * Allocation by geography.
     *
     * @return list<array{geography: string, value: float, percent: float, count: int}>
     */
    public function geographyAllocation(string $userId, string $baseCurrency = 'EUR'): array
    {
        $investments = $this->loadActiveInvestments($userId);
        $totalValue = $investments->map(fn (Investment $i) => $this->valueInCurrency($i, $baseCurrency))->sum();

        return $investments
            ->groupBy(fn (Investment $i) => $i->geography ?? 'Non défini')
            ->map(function (Collection $group) use ($totalValue, $baseCurrency) {
                $value = $group->sum(fn (Investment $i) => $this->valueInCurrency($i, $baseCurrency));

                return [
                    'geography' => $group->first()->geography ?? 'Non défini',
                    'value' => round($value, 2),
                    'percent' => $totalValue > 0 ? round($value / $totalValue * 100, 2) : 0.0,
                    'count' => $group->count(),
                ];
            })
            ->sortByDesc('value')
            ->values()
            ->all();
    }

    /**
     * Allocation by sector (from sector_allocations on each investment).
     *
     * @return list<array{sector: string, value: float, percent: float, count: int}>
     */
    public function sectorAllocation(string $userId, string $baseCurrency = 'EUR'): array
    {
        $investments = $this->loadActiveInvestments($userId);
        $totalValue = $investments->map(fn (Investment $i) => $this->valueInCurrency($i, $baseCurrency))->sum();
        $sectorTotals = [];
        $sectorCounts = [];

        foreach ($investments as $i) {
            $value = $this->valueInCurrency($i, $baseCurrency);
            $allocations = $i->sector_allocations;

            if (! empty($allocations) && is_array($allocations)) {
                foreach ($allocations as $alloc) {
                    $sector = trim((string) ($alloc['sector'] ?? ''));
                    $percent = (float) ($alloc['percent'] ?? 0);
                    if ($sector !== '' && $percent > 0) {
                        $sectorTotals[$sector] = ($sectorTotals[$sector] ?? 0) + ($value * $percent / 100);
                        $sectorCounts[$sector] = ($sectorCounts[$sector] ?? 0) + 1;
                    }
                }
            }
        }

        $result = [];
        foreach ($sectorTotals as $sector => $val) {
            $result[] = [
                'sector' => $sector,
                'value' => round($val, 2),
                'percent' => $totalValue > 0 ? round($val / $totalValue * 100, 2) : 0.0,
                'count' => $sectorCounts[$sector] ?? 1,
            ];
        }

        usort($result, fn ($a, $b) => $b['value'] <=> $a['value']);

        return $result;
    }

    /**
     * Time series of the portfolio total value.
     *
     * @return list<array{date: string, total_value: float, total_cost: float}>
     */
    public function performance(string $userId, string $range = 'all'): SupportCollection
    {
        $query = PortfolioSnapshot::where('user_id', $userId);

        $months = match ($range) {
            '1m' => 1,
            '3m' => 3,
            '6m' => 6,
            '1y' => 12,
            default => null,
        };

        if ($months !== null) {
            $query->where('snapshot_date', '>=', now()->subMonths($months)->toDateString());
        }

        return $query->orderBy('snapshot_date')
            ->get()
            ->map(fn (PortfolioSnapshot $s) => [
                'date' => is_string($s->snapshot_date) ? $s->snapshot_date : $s->snapshot_date->toDateString(),
                'total_value' => (float) $s->total_value,
                'total_cost' => (float) $s->total_cost,
            ]);
    }

    /**
     * @return Collection<int, Investment>
     */
    private function loadActiveInvestments(string $userId): Collection
    {
        return Investment::forUser($userId)
            ->where('status', 'active')
            ->with(['assetType', 'latestPrice.provider'])
            ->get();
    }

    /**
     * @return array{current_value: float, purchase_value: float|null, pnl_absolute: float|null, pnl_percent: float|null}
     */
    private function computeRow(Investment $i): array
    {
        $currentValue = $this->currentValue($i);
        $purchaseValue = $i->purchase_price !== null
            ? (float) $i->quantity * (float) $i->purchase_price
            : null;
        $pnlAbsolute = ($purchaseValue !== null) ? $currentValue - $purchaseValue : null;
        $pnlPercent = ($pnlAbsolute !== null && $purchaseValue > 0)
            ? ($pnlAbsolute / $purchaseValue) * 100
            : null;

        return [
            'current_value' => $currentValue,
            'purchase_value' => $purchaseValue,
            'pnl_absolute' => $pnlAbsolute,
            'pnl_percent' => $pnlPercent,
        ];
    }

    private function currentValue(Investment $i): float
    {
        $latest = $i->latestPrice?->first();

        return $this->valuation->currentValue($i, $latest?->price !== null ? (float) $latest->price : null);
    }

    private function normalizeRow(array $row, Investment $i, string $targetCurrency): array
    {
        if ($i->currency === $targetCurrency) {
            return $row;
        }

        $valueConv = $this->fxService->convert($row['current_value'], $i->currency, $targetCurrency);
        $costConv = $row['purchase_value'] !== null
            ? $this->fxService->convert($row['purchase_value'], $i->purchase_currency ?? $i->currency, $targetCurrency)
            : null;

        $currentValue = $valueConv['rate'] !== null ? $valueConv['amount'] : $row['current_value'];
        $purchaseValue = $costConv['rate'] !== null && $costConv !== null ? $costConv['amount'] : $row['purchase_value'];
        $pnlAbsolute = ($purchaseValue !== null) ? $currentValue - $purchaseValue : null;
        $pnlPercent = ($pnlAbsolute !== null && $purchaseValue > 0)
            ? ($pnlAbsolute / $purchaseValue) * 100
            : null;

        return [
            'current_value' => $currentValue,
            'purchase_value' => $purchaseValue,
            'pnl_absolute' => $pnlAbsolute,
            'pnl_percent' => $pnlPercent,
        ];
    }

    private function valueInCurrency(Investment $i, string $targetCurrency): float
    {
        $value = $this->currentValue($i);
        if ($i->currency === $targetCurrency) {
            return $value;
        }

        $conv = $this->fxService->convert($value, $i->currency, $targetCurrency);

        return $conv['rate'] !== null ? $conv['amount'] : $value;
    }
}
