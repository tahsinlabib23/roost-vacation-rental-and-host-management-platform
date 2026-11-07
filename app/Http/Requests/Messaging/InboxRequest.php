<?php

namespace App\Http\Requests\Messaging;

use Illuminate\Foundation\Http\FormRequest;

class InboxRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['guest', 'host'], true);
    }

    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['prohibited'],
        ];
    }
}
