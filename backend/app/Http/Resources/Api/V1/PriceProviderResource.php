<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\PriceProvider */
class PriceProviderResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'label' => $this->label,
            'base_url' => $this->base_url,
            'rate_limit_per_min' => $this->rate_limit_per_min,
            'is_active' => $this->is_active,
            'priority' => $this->priority,
            // Never expose api_key_env (internal config) to the client.
        ];
    }
}
