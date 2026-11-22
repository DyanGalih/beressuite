<?php

namespace App\Http\Middleware;

use App\Models\PersonalAccessToken;
use Closure;
use Illuminate\Http\Request;

class EnforceTokenAbilities
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $token = $user->currentAccessToken();

        if (! ($token instanceof PersonalAccessToken)) {
            return $next($request);
        }

        $isPinned = is_numeric($token->company_id);
        $isWildcard = $token->can('*');

        if (! $isPinned && $isWildcard) {
            return $next($request);
        }

        $requiredAbility = $this->resolveRequiredAbility($request);

        if ($requiredAbility === null || ! $token->can($requiredAbility)) {
            abort(403, 'Token does not have the required ability for this endpoint.');
        }

        return $next($request);
    }

    private function resolveRequiredAbility(Request $request): ?string
    {
        $path = trim($request->path(), '/');

        if (! str_starts_with($path, 'api/v1/')) {
            return null;
        }

        $relative = substr($path, strlen('api/v1/'));
        $action = in_array(strtoupper($request->method()), ['GET', 'HEAD'], true) ? 'read' : 'write';

        $exactRouteMap = config('api_tokens.exact_route_map', []);

        if (isset($exactRouteMap[$relative]) && is_array($exactRouteMap[$relative])) {
            return $exactRouteMap[$relative][$action] ?? null;
        }

        $segment = explode('/', $relative)[0] ?? '';

        if ($segment === '') {
            return null;
        }

        $routeMap = config('api_tokens.route_map', []);

        if (! isset($routeMap[$segment]) || ! is_array($routeMap[$segment])) {
            return null;
        }

        return $routeMap[$segment][$action] ?? null;
    }
}
