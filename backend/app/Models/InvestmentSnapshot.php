<?php

namespace App\Models;

use App\Support\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvestmentSnapshot extends Model
{
    use HasUuid;

    protected $fillable = [
        'investment_id', 'user_id', 'snapshot_date', 'quantity',
        'price', 'value', 'cost', 'currency',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:6',
            'price' => 'decimal:6',
            'value' => 'decimal:6',
            'cost' => 'decimal:6',
        ];
    }

    public function investment(): BelongsTo
    {
        return $this->belongsTo(Investment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
