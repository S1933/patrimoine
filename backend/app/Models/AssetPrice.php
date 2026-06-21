<?php

namespace App\Models;

use App\Support\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $investment_id
 * @property string|null $provider_id
 * @property string $price
 * @property string $currency
 * @property string $fetched_at
 * @property string $source_status
 * @property string|null $error_message
 * @property array|null $raw_payload
 */
class AssetPrice extends Model
{
    use HasUuid;

    public $timestamps = false;

    protected $fillable = [
        'investment_id', 'provider_id', 'price', 'currency',
        'fetched_at', 'source_status', 'error_message', 'raw_payload',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:6',
            'fetched_at' => 'datetime',
            'raw_payload' => 'array',
        ];
    }

    public function investment(): BelongsTo
    {
        return $this->belongsTo(Investment::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(PriceProvider::class);
    }
}
