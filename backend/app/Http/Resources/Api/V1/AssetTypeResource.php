<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\AssetType */
class AssetTypeResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'label' => $this->label,
            'default_provider' => $this->default_provider,
            'default_unit' => $this->default_unit,
            'is_priced_externally' => $this->is_priced_externally,
        ];
    }
}
