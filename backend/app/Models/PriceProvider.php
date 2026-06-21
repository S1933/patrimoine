<?php

namespace App\Models;

use App\Support\HasUuid;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $code
 * @property string $label
 * @property int $supported_types
 * @property string|null $base_url
 * @property string|null $api_key_env
 * @property int $rate_limit_per_min
 * @property bool $is_active
 * @property int $priority
 */
class PriceProvider extends Model
{
    use HasUuid;

    protected $fillable = [
        'code', 'label', 'supported_types', 'base_url',
        'api_key_env', 'rate_limit_per_min', 'is_active', 'priority',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'supported_types' => 'integer',
            'rate_limit_per_min' => 'integer',
            'priority' => 'integer',
        ];
    }

    public function prices(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AssetPrice::class, 'provider_id');
    }

    public function investments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Investment::class, 'provider_id');
    }
}
