<?php

namespace App\Http\Middleware;

use App\Models\PersonalAccessToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class CompanyMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if (Schema::hasTable('user_company')) {
            $user = $request->user();

            if (! $user) {
                return $next($request);
            }

            $token = $user?->currentAccessToken();

            if ($token instanceof PersonalAccessToken && is_numeric($token->company_id)) {
                $pinnedCompanyId = (int) $token->company_id;

                if (! $user->hasCompany($pinnedCompanyId)) {
                    abort(403, 'Token owner does not belong to the token company.');
                }

                $requestedCompany = $request->header('company');

                if ($requestedCompany !== null && $requestedCompany !== '' && (string) $requestedCompany !== (string) $pinnedCompanyId) {
                    abort(403, 'Token is not authorized for the requested company.');
                }

                $request->headers->set('company', (string) $pinnedCompanyId);

                return $next($request);
            }

            if ((! $request->header('company')) || (! $user->hasCompany($request->header('company')))) {
                $request->headers->set('company', $user->companies()->first()->id);
            }
        }

        return $next($request);
    }
}
