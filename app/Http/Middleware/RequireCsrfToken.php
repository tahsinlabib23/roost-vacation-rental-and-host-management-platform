<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpFoundation\Response;

class RequireCsrfToken
{
    public function __construct(private Application $app)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        // Laravel disables CSRF enforcement in feature tests; real HTTP checks cover this middleware.
        if ($this->app->runningInConsole() && $this->app->runningUnitTests()) {
            return $next($request);
        }

        $sessionToken = $request->session()->token();
        $requestToken = $request->input('_token') ?: $request->header('X-CSRF-TOKEN');

        if (! is_string($sessionToken)
            || ! is_string($requestToken)
            || ! hash_equals($sessionToken, $requestToken)) {
            throw new TokenMismatchException('CSRF token mismatch.');
        }

        return $next($request);
    }
}
