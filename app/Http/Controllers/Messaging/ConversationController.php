<?php

namespace App\Http\Controllers\Messaging;

use App\Exceptions\MessageConflictException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Messaging\InboxRequest;
use App\Http\Requests\Messaging\StartConversationRequest;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Property;
use App\Services\MessagingQueryService;
use App\Services\MessagingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ConversationController extends Controller
{
    public function store(
        StartConversationRequest $request,
        MessagingService $messaging,
        MessagingQueryService $queries,
    ): JsonResponse|RedirectResponse {
        $validated = $request->validated();

        try {
            $message = $messaging->startConversation(
                $request->user(),
                Property::query()->findOrFail($validated['property_id']),
                $validated['body'],
                $validated['client_message_id'],
            );
        } catch (MessageConflictException $exception) {
            if ($request->expectsJson()) {
                throw $exception;
            }

            return back()->withErrors(['body' => $exception->getMessage()])->withInput();
        }

        if ($request->expectsJson()) {
            return response()->json([
                'conversation' => $queries->conversation($message->conversation, $request->user()),
                'message' => $this->messagePayload($message, $request->user()->id),
            ], 201);
        }

        return redirect()
            ->route('messages.show', $message->conversation)
            ->with('status', 'Your message was sent.');
    }

    public function show(
        InboxRequest $request,
        Conversation $conversation,
        MessagingQueryService $queries,
    ): View {
        Gate::authorize('view', $conversation);

        return view('messages.index', [
            'user' => $request->user(),
            'isHost' => $request->user()->role === 'host',
            'inbox' => $queries->inbox($request->user()),
            'selectedConversation' => $queries->conversation($conversation, $request->user()),
            'history' => $queries->history($conversation, $request->user()),
            'searchResults' => null,
        ]);
    }

    private function messagePayload(Message $message, int $userId): array
    {
        return [
            'id' => $message->id,
            'conversation_id' => $message->conversation_id,
            'sender_id' => $message->sender_id,
            'body' => $message->body,
            'read_at' => $message->read_at?->toISOString(),
            'created_at' => $message->created_at->toISOString(),
            'is_mine' => $message->sender_id === $userId,
        ];
    }
}
