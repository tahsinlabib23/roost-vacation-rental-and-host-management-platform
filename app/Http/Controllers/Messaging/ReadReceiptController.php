<?php

namespace App\Http\Controllers\Messaging;

use App\Http\Controllers\Controller;
use App\Http\Requests\Messaging\MarkConversationReadRequest;
use App\Models\Conversation;
use App\Services\MessagingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class ReadReceiptController extends Controller
{
    public function __invoke(
        MarkConversationReadRequest $request,
        Conversation $conversation,
        MessagingService $messaging,
    ): JsonResponse|RedirectResponse {
        Gate::authorize('markRead', $conversation);
        $updated = $messaging->markRead(
            $request->user(),
            $conversation,
            (int) $request->validated('through_message_id'),
        );

        if ($request->expectsJson()) {
            return response()->json(['updated' => $updated]);
        }

        return redirect()->route('messages.show', $conversation);
    }
}
