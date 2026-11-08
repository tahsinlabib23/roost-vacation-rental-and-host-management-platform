<?php

namespace App\Providers;

use App\Models\Conversation;
use App\Policies\ConversationPolicy;
use App\View\Composers\AppLayoutComposer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(Conversation::class, ConversationPolicy::class);

        RateLimiter::for('messaging-search', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->getAuthIdentifier() ?? $request->ip());
        });

        RateLimiter::for('messaging-send', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->getAuthIdentifier() ?? $request->ip());
        });

        View::composer('layouts.app', AppLayoutComposer::class);
    }
}
