<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateInvestmentStrategyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*.asset_type_id' => ['required', 'integer', 'distinct', 'exists:asset_types,id'],
            'allocations.*.target_percent' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:100'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $total = collect($this->input('allocations', []))
                    ->sum(fn (array $allocation) => (float) $allocation['target_percent']);

                if (round($total, 2) !== 100.0) {
                    $validator->errors()->add(
                        'allocations',
                        'Le total de la stratégie doit être égal à 100 %.',
                    );
                }
            },
        ];
    }
}
