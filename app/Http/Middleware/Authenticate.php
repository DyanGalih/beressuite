<?php

namespace Crater\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;

class Authenticate extends Middleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string[]  ...$guards
     * @return mixed
     */
    public function handle($request, \Closure $next, ...$guards)
    {
        if (config('api_tokens.public_openapi', false) && $request->is('api/v1/openapi.yaml')) {
            try {
                $this->authenticate($request, $guards);
            } catch (\Illuminate\Auth\AuthenticationException) {
                // Public access allowed when unauthenticated
            }

            return $next($request);
        }

        $this->authenticate($request, $guards);

        return $next($request);
    }

    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string
     */
    protected function redirectTo($request)
    {
        if (! $request->expectsJson()) {
            return route('login');
        }
    }
}
