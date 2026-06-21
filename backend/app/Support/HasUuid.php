<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Auto-generates a UUID (version 4) as the primary key on creating.
 * Models using this trait MUST have a uuid primary key column named `id`.
 */
trait HasUuid
{
    public function initializeHasUuid(): void
    {
        $this->incrementing = false;
        $this->keyType = 'string';
    }

    protected static function bootHasUuid(): void
    {
        static::creating(function (self $model) {
            if ($model->getKey() === null) {
                $model->setAttribute($model->getKeyName(), (string) Str::uuid());
            }
        });
    }
}
