<?php

namespace App\Http\Controllers\Messaging;

use App\Http\Controllers\Controller;
use App\Http\Requests\Messaging\InboxRequest;
use App\Services\MessagingQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class InboxController extends Controller
{
    public function index(InboxRequest $request, MessagingQueryService $queries): View
    {
        return view('messages.index', [
            'user' => $request->user(),
            'isHost' => $request->user()->role === 'host',
            'inbox' => $queries->inbox($request->user()),
            'selectedConversation' => null,
            'history' => null,
            'searchResults' => null,
        ]);
    }

    public function poll(InboxRequest $request, MessagingQueryService $queries): JsonResponse
    {
        return response()->json($queries->paginatorPayload($queries->inbox($request->user())));
    }
}
