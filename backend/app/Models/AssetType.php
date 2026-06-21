<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $code
 * @property string $label
 * @property string|null $default_provider
 * @property string|null $default_unit
 * @property bool $is_priced_externally
 */
class AssetType extends Model
{
    public $timestamps = true;

    protected $table = 'asset_types';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'code', 'label', 'default_provider', 'default_unit', 'is_priced_externally',
    ];

    protected function casts(): array
    {
        return [
            'is_priced_externally' => 'boolean',
        ];
    }

    public function investments(): HasMany
    {
        return $this->hasMany(Investment::class, 'asset_type_id');
    }

    public function investmentStrategyAllocations(): HasMany
    {
        return $this->hasMany(InvestmentStrategyAllocation::class);
    }
}
