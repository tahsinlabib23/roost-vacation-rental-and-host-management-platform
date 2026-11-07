<?php

namespace App\Http\Requests\Messaging;

use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'guest';
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
            'property_id' => [
                'required',
                'integer',
                Rule::exists('properties', 'id')->where(function (Builder $query) {
                    $query
                        ->where('status', 'published')
                        ->whereExists(function (Builder $hostQuery) {
                            $hostQuery
                                ->selectRaw('1')
                                ->from('users')
                                ->whereColumn('users.id', 'properties.host_id')
                                ->where('users.role', 'host');
                        });
                }),
            ],
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
