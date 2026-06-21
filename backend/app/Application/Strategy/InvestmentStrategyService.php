<?php

namespace App\Application\Strategy;

use App\Models\AssetType;
use App\Models\InvestmentStrategyAllocation;
use Illuminate\Support\Facades\DB;

final class InvestmentStrategyService
{
    /**
     * @return array{allocations: list<array{asset_type_id: int, code: string, label: string, target_percent: float}>, total_percent: float}
     */
    public function get(string $userId): array
    {
        $targets = InvestmentStrategyAllocation::query()
            ->where('user_id', $userId)
            ->pluck('target_percent', 'asset_type_id');

        $allocations = AssetType::query()
            ->orderBy('id')
            ->get()
            ->map(fn (AssetType $type) => [
                'asset_type_id' => $type->id,
                'code' => $type->code,
                'label' => $type->label,
                'target_percent' => (float) ($targets[$type->id] ?? 0),
            ])
            ->all();

        return [
            'allocations' => $allocations,
            'total_percent' => round(array_sum(array_column($allocations, 'target_percent')), 2),
        ];
    }

    /**
     * @param  list<array{asset_type_id: int, target_percent: float|int|string}>  $allocations
     * @return array{allocations: list<array{asset_type_id: int, code: string, label: string, target_percent: float}>, total_percent: float}
     */
    public function replace(string $userId, array $allocations): array
    {
        DB::transaction(function () use ($userId, $allocations) {
            InvestmentStrategyAllocation::query()
                ->where('user_id', $userId)
                ->delete();

            $now = now();
            $rows = collect($allocations)
                ->filter(fn (array $allocation) => (float) $allocation['target_percent'] > 0)
                ->map(fn (array $allocation) => [
                    'user_id' => $userId,
                    'asset_type_id' => $allocation['asset_type_id'],
                    'target_percent' => $allocation['target_percent'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
                ->all();

            if ($rows !== []) {
                InvestmentStrategyAllocation::query()->insert($rows);
            }
        });

        return $this->get($userId);
    }
}
