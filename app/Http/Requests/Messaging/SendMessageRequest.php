<?php

namespace App\Http\Requests\Messaging;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class SendMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $conversation = $this->route('conversation');

        return $conversation !== null && Gate::allows('send', $conversation);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'body' => is_string($this->body) ? trim($this->body) : $this->body,
        ]);
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'min:1', 'max:2000'],
            'client_message_id' => ['required', 'uuid'],
            'guest_id' => ['prohibited'],
            'host_id' => ['prohibited'],
            'sender_id' => ['prohibited'],
            'recipient_id' => ['prohibited'],
            'conversation_id' => ['prohibited'],
            'role' => ['prohibited'],
        ];
    }
}
