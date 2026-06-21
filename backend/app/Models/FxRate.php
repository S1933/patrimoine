<?php

namespace App\Models;

use App\Support\HasUuid;
use Illuminate\Database\Eloquent\Model;

class FxRate extends Model
{
    use HasUuid;

    protected $fillable = [
        'from_currency', 'to_currency', 'rate', 'source', 'fetched_at',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:10',
            'fetched_at' => 'datetime',
        ];
    }
}
