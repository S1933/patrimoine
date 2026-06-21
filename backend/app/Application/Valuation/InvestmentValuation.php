<?php

namespace App\Application\Valuation;

use App\Models\Investment;

final class InvestmentValuation
{
    private const FIXED_YIELD_ASSETS = ['livret_a', 'ldds'];

    private const FIXED_YIELD_RATE = 0.015;

    public function currentValue(Investment $investment, ?float $marketPrice = null): float
    {
        if ($this->isFixedYieldSavings($investment)) {
            return $this->savingsValue($investment);
        }

        if ($investment->manual_value !== null) {
            return (float) $investment->manual_value;
        }

        if ($marketPrice !== null) {
            return (float) $investment->quantity * $marketPrice;
        }

        return 0.0;
    }

    public function savingsYield(Investment $investment): ?array
    {
        if (! $this->isFixedYieldSavings($investment) || $investment->manual_value === null) {
            return null;
        }

        $baseValue = (float) $investment->manual_value;
        $days = $this->daysSinceOpening($investment);
        if ($days <= 0) {
            return [
                'rate' => self::FIXED_YIELD_RATE,
                'base_value' => round($baseValue, 2),
                'accrued_interest' => 0.0,
                'days' => max($days, 0),
                'current_value' => round($baseValue, 2),
            ];
        }

        $accruedInterest = $baseValue * self::FIXED_YIELD_RATE * ($days / 365);

        return [
            'rate' => self::FIXED_YIELD_RATE,
            'base_value' => round($baseValue, 2),
            'accrued_interest' => round($accruedInterest, 2),
            'days' => $days,
            'current_value' => round($baseValue + $accruedInterest, 2),
        ];
    }

    public function savingsRate(Investment $investment): ?float
    {
        return $this->isFixedYieldSavings($investment) ? self::FIXED_YIELD_RATE : null;
    }

    private function isFixedYieldSavings(Investment $investment): bool
    {
        return in_array($investment->assetType->code, self::FIXED_YIELD_ASSETS, true);
    }

    private function savingsValue(Investment $investment): float
    {
        if ($investment->manual_value === null) {
            return 0.0;
        }

        $baseValue = (float) $investment->manual_value;
        $days = $this->daysSinceOpening($investment);
        if ($days <= 0) {
            return round($baseValue, 2);
        }

        $accruedInterest = $baseValue * self::FIXED_YIELD_RATE * ($days / 365);

        return round($baseValue + $accruedInterest, 2);
    }

    private function daysSinceOpening(Investment $investment): int
    {
        $openedAt = $investment->purchase_date;
        if ($openedAt === null) {
            return 0;
        }

        $date = $openedAt instanceof \DateTimeInterface ? $openedAt : new \DateTimeImmutable((string) $openedAt);
        $diff = $date->diff(now());

        return max(0, (int) $diff->days);
    }
}
