<?php

namespace App\Models;

use App\Support\HasUuid;
use Database\Factories\InvestmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $user_id
 * @property int $asset_type_id
 * @property string $name
 * @property string|null $isin
 * @property string|null $symbol
 * @property string $quantity
 * @property string $unit
 * @property string|null $geography
 * @property array|null $country_allocations
 * @property array|null $sector_allocations
 * @property string|null $purchase_price
 * @property string|null $purchase_currency
 * @property string|null $purchase_date
 * @property string|null $manual_value
 * @property string|null $manual_value_updated_at
 * @property string $currency
 * @property string|null $provider_id
 * @property string|null $notes
 * @property string $status
 */
class Investment extends Model
{
    /** @use HasFactory<InvestmentFactory> */
    use HasFactory, HasUuid, SoftDeletes;

    protected $fillable = [
        'user_id', 'asset_type_id', 'name', 'isin', 'symbol', 'quantity', 'unit', 'geography', 'country_allocations', 'sector_allocations',
        'purchase_price', 'purchase_currency', 'purchase_date',
        'manual_value', 'manual_value_updated_at', 'currency',
        'provider_id', 'notes', 'status',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:6',
            'purchase_price' => 'decimal:6',
            'manual_value' => 'decimal:6',
            'purchase_date' => 'date',
            'country_allocations' => 'array',
            'sector_allocations' => 'array',
            'manual_value_updated_at' => 'datetime',
            'notes' => 'encrypted',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assetType(): BelongsTo
    {
        return $this->belongsTo(AssetType::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(PriceProvider::class, 'provider_id');
    }

    public function prices(): HasMany
    {
        return $this->hasMany(AssetPrice::class)->orderByDesc('fetched_at');
    }

    public function latestPrice(): HasMany
    {
        return $this->prices()->limit(1);
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(InvestmentSnapshot::class)->orderByDesc('snapshot_date');
    }

    public function scopeForUser(Builder $query, string $userId): Builder
    {
        return $query->where('user_id', $userId);
    }
}
