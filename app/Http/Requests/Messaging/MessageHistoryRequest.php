<?php

namespace App\Http\Requests\Messaging;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class MessageHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $conversation = $this->route('conversation');

        return $conversation !== null && Gate::allows('poll', $conversation);
    }

    public function rules(): array
    {
        return [
            'before_id' => ['nullable', 'integer', 'min:0', 'prohibits:after_id'],
            'after_id' => ['nullable', 'integer', 'min:0', 'prohibits:before_id'],
            'per_page' => ['prohibited'],
        ];
    }
}
