<?php

namespace App\Models;

use App\Support\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PortfolioSnapshot extends Model
{
    use HasUuid;

    protected $fillable = [
        'user_id', 'snapshot_date', 'total_value', 'total_cost',
        'currency', 'fx_rate', 'fx_source', 'fx_from_currency', 'active_count',
    ];

    protected function casts(): array
    {
        return [
            'total_value' => 'decimal:6',
            'total_cost' => 'decimal:6',
            'active_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
