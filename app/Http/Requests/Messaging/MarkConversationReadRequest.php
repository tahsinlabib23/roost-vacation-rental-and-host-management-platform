<?php

namespace App\Http\Requests\Messaging;

use App\Models\Conversation;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class MarkConversationReadRequest extends FormRequest
{
    public function authorize(): bool
    {
        $conversation = $this->route('conversation');

        return $conversation !== null && Gate::allows('markRead', $conversation);
    }

    public function rules(): array
    {
        $conversation = $this->route('conversation');
        $conversationId = $conversation instanceof Conversation ? $conversation->id : $conversation;

        return [
            'through_message_id' => [
                'required',
                'integer',
                'min:0',
                Rule::exists('messages', 'id')->where(
                    fn (Builder $query) => $query->where('conversation_id', $conversationId)
                ),
            ],
        ];
    }
}
