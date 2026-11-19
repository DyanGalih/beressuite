<?php

namespace Crater\Http\Middleware;

use Closure;
use Crater\Providers\AppServiceProvider;
use Illuminate\Support\Facades\Auth;

class RedirectIfAuthenticated
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string|null  $guard
     * @return mixed
     */
    public function handle($request, Closure $next, $guard = null)
    {
        if (Auth::guard($guard)->check()) {
            return redirect(AppServiceProvider::HOME);
        }

        return $next($request);
    }
}
