<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreChatSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'opencode_api_key' => ['nullable', 'string', 'min:8', 'max:512'],
            'opencode_model' => ['nullable', 'string', 'max:80'],
            'opencode_provider' => ['nullable', 'string', 'in:zen,go'],
        ];
    }

    public function messages(): array
    {
        return [
            'opencode_api_key.min' => 'La clé API semble invalide (trop courte).',
            'opencode_provider.in' => 'Le provider OpenCode sélectionné est invalide.',
        ];
    }
}
