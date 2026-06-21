<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreChatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'messages' => ['required', 'array', 'min:1', 'max:50'],
            'messages.*.role' => ['required', 'string', 'in:user,assistant'],
            'messages.*.content' => ['required', 'string', 'max:8000'],
            'model' => ['nullable', 'string', 'max:80'],
        ];
    }

    public function messages(): array
    {
        return [
            'messages.required' => 'Les messages sont requis.',
            'messages.max' => 'Trop de messages (max 50).',
            'messages.*.role.in' => 'Rôle invalide (user ou assistant).',
            'messages.*.content.max' => 'Message trop long (max 8000 caractères).',
        ];
    }
}
