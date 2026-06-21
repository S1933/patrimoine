<?php

namespace App\Models;

use App\Support\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiSyncLog extends Model
{
    use HasUuid;

    public $timestamps = false;

    protected $fillable = [
        'run_id', 'provider_id', 'investment_id', 'status',
        'duration_ms', 'error_message', 'http_status', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'duration_ms' => 'integer',
            'http_status' => 'integer',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(PriceProvider::class);
    }

    public function investment(): BelongsTo
    {
        return $this->belongsTo(Investment::class);
    }
}
