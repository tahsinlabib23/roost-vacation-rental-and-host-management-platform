<?php

namespace App\Http\Controllers\Messaging;

use App\Http\Controllers\Controller;
use App\Http\Requests\Messaging\SearchHostsRequest;
use App\Services\MessagingQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class HostSearchController extends Controller
{
    public function __invoke(SearchHostsRequest $request, MessagingQueryService $queries): JsonResponse|View
    {
        $validated = $request->validated();
        $results = $queries->searchHosts(
            $request->user(),
            $validated['host_name'],
            $validated['property_name'],
        );

        if ($request->expectsJson()) {
            return response()->json($queries->paginatorPayload($results));
        }

        return view('messages.index', [
            'user' => $request->user(),
            'isHost' => false,
            'inbox' => $queries->inbox($request->user()),
            'selectedConversation' => null,
            'history' => null,
            'searchResults' => $results,
        ]);
    }
}
