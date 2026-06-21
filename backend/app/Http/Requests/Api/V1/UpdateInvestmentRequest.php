<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInvestmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'asset_type_id' => ['sometimes', 'integer', 'exists:asset_types,id'],
            'name' => ['sometimes', 'string', 'max:120'],
            'isin' => ['nullable', 'string', 'size:12', 'regex:/^[A-Z]{2}[A-Z0-9]{10}$/'],
            'symbol' => ['nullable', 'string', 'max:40'],
            'quantity' => ['sometimes', 'numeric', 'gt:0', 'max:999999999999.999999'],
            'unit' => ['sometimes', 'string', 'max:20'],
            'geography' => ['nullable', 'string', 'max:50'],
            'country_allocations' => ['nullable', 'array'],
            'country_allocations.*.country' => ['required_with:country_allocations', 'string', 'size:3'],
            'country_allocations.*.percent' => ['required_with:country_allocations', 'numeric', 'min:0', 'max:100'],
            'sector_allocations' => ['nullable', 'array'],
            'sector_allocations.*.sector' => ['required_with:sector_allocations', 'string', 'max:50'],
            'sector_allocations.*.percent' => ['required_with:sector_allocations', 'numeric', 'min:0', 'max:100'],
            'purchase_price' => ['nullable', 'numeric', 'gt:0'],
            'purchase_currency' => ['nullable', 'string', 'size:3'],
            'purchase_date' => ['nullable', 'date', 'before_or_equal:today'],
            'manual_value' => ['nullable', 'numeric', 'gt:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'provider_id' => ['nullable', 'uuid', 'exists:price_providers,id'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'status' => ['sometimes', 'string', Rule::in(['active', 'sold', 'archived'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'isin' => $this->filled('isin') ? strtoupper(trim((string) $this->input('isin'))) : null,
        ]);
    }
}
