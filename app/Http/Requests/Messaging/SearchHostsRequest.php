<?php

namespace App\Http\Requests\Messaging;

use Illuminate\Foundation\Http\FormRequest;

class SearchHostsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'guest';
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'host_name' => is_string($this->host_name) ? trim($this->host_name) : $this->host_name,
            'property_name' => is_string($this->property_name) ? trim($this->property_name) : $this->property_name,
        ]);
    }

    public function rules(): array
    {
        return [
            'host_name' => ['required', 'string', 'min:2', 'max:120'],
            'property_name' => ['required', 'string', 'min:2', 'max:160'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['prohibited'],
        ];
    }
}
