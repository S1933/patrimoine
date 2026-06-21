<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvestmentStrategyAllocation extends Model
{
    protected $fillable = [
        'user_id', 'asset_type_id', 'target_percent',
    ];

    protected function casts(): array
    {
        return [
            'target_percent' => 'decimal:2',
        ];
    }

    public function assetType(): BelongsTo
    {
        return $this->belongsTo(AssetType::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
