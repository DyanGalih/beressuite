<?php

namespace Crater\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PdfMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        // Exclude customer authentication tokens from generic PDF access
        if (Auth::guard('customer')->check() && ! Auth::guard('sanctum')->check() && ! Auth::guard('web')->check()) {
            return redirect('/login');
        }

        $user = Auth::guard('sanctum')->user() ?: Auth::guard('web')->user();

        if ($user) {
            $companyId = $request->header('company');
            if ($companyId && ! $user->hasCompany($companyId)) {
                abort(403, 'Unauthorized company access.');
            }

            return $next($request);
        }

        return redirect('/login');
    }
}
