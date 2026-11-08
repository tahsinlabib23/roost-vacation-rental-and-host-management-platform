<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Messaging\ConversationController;
use App\Http\Controllers\Messaging\HostSearchController;
use App\Http\Controllers\Messaging\InboxController;
use App\Http\Controllers\Messaging\MessageController;
use App\Http\Controllers\Messaging\ReadReceiptController;
use App\Http\Middleware\PrivateMessagingResponse;
use App\Http\Middleware\RequireCsrfToken;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
})->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('login.store');

    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('register.store');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::middleware(PrivateMessagingResponse::class)->group(function () {
        Route::get('/messages', [InboxController::class, 'index'])->name('messages.index');
        Route::get('/messages/search', HostSearchController::class)
            ->middleware('throttle:messaging-search')
            ->name('messages.search');
        Route::get('/messages/inbox', [InboxController::class, 'poll'])->name('messages.inbox');
        Route::post('/messages/conversations', [ConversationController::class, 'store'])
            ->middleware([RequireCsrfToken::class, 'throttle:messaging-send'])
            ->name('messages.conversations.store');
        Route::get('/messages/conversations/{conversation}', [ConversationController::class, 'show'])
            ->whereNumber('conversation')
            ->name('messages.show');
        Route::get('/messages/conversations/{conversation}/items', [MessageController::class, 'index'])
            ->whereNumber('conversation')
            ->name('messages.items');
        Route::post('/messages/conversations/{conversation}/items', [MessageController::class, 'store'])
            ->whereNumber('conversation')
            ->middleware([RequireCsrfToken::class, 'throttle:messaging-send'])
            ->name('messages.store');
        Route::post('/messages/conversations/{conversation}/read', ReadReceiptController::class)
            ->whereNumber('conversation')
            ->middleware(RequireCsrfToken::class)
            ->name('messages.read');
    });

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
// Add this to the very bottom of routes/web.php

Route::post('/wishlist/{propertyId}', function (\Illuminate\Http\Request $request, $propertyId) {
    $wishlist = $request->session()->get('wishlist', []);

    if (in_array($propertyId, $wishlist)) {
        // Remove it if it's already there
        $wishlist = array_diff($wishlist, [$propertyId]);
    } else {
        // Add it if it's not
        $wishlist[] = $propertyId;
    }

    $request->session()->put('wishlist', $wishlist);

    return back()->with('status', 'Wishlist updated!');
})->name('wishlist.toggle');

Route::get('/wishlist', function (\Illuminate\Http\Request $request) {
    $wishlistIds = $request->session()->get('wishlist', []);
    
    // Fetch only the properties that match the IDs in the session
    $properties = \App\Models\Property::whereIn('id', $wishlistIds)->get();
    
    // Note: You'll need to create this view file next!
    return view('properties.wishlist', compact('properties'));
});