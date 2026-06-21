<?php

namespace App\Models;

use App\Support\HasUuid;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'base_currency', 'opencode_api_key', 'opencode_model', 'opencode_provider'])]
#[Hidden(['password', 'remember_token', 'opencode_api_key'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasUuid, Notifiable;

    public const DEFAULT_CURRENCY = 'EUR';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'base_currency' => 'string',
            'opencode_api_key' => 'encrypted',
        ];
    }

    public function investmentStrategyAllocations(): HasMany
    {
        return $this->hasMany(InvestmentStrategyAllocation::class);
    }
}
