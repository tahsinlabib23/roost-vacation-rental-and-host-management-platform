<?php

namespace App\Http\Controllers\Messaging;

use App\Exceptions\MessageConflictException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Messaging\MessageHistoryRequest;
use App\Http\Requests\Messaging\SendMessageRequest;
use App\Models\Conversation;
use App\Services\MessagingQueryService;
use App\Services\MessagingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class MessageController extends Controller
{
    public function index(
        MessageHistoryRequest $request,
        Conversation $conversation,
        MessagingQueryService $queries,
    ): JsonResponse {
        Gate::authorize('poll', $conversation);
        $validated = $request->validated();

        return response()->json($queries->history(
            $conversation,
            $request->user(),
            isset($validated['before_id']) ? (int) $validated['before_id'] : null,
            isset($validated['after_id']) ? (int) $validated['after_id'] : null,
        ));
    }

    public function store(
        SendMessageRequest $request,
        Conversation $conversation,
        MessagingService $messaging,
    ): JsonResponse|RedirectResponse {
        Gate::authorize('send', $conversation);
        $validated = $request->validated();

        try {
            $message = $messaging->sendMessage(
                $request->user(),
                $conversation,
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
                'message' => [
                    'id' => $message->id,
                    'conversation_id' => $message->conversation_id,
                    'sender_id' => $message->sender_id,
                    'body' => $message->body,
                    'read_at' => $message->read_at?->toISOString(),
                    'created_at' => $message->created_at->toISOString(),
                    'is_mine' => true,
                ],
            ], 201);
        }

        return redirect()
            ->route('messages.show', $conversation)
            ->with('status', 'Message sent.');
    }
}
