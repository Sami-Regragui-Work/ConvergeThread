<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IdentifyTenant
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        if ($user->tenant_id === null) {
            abort(403, 'User is not attached to any tenant.');
        }

        $tenant = Tenant::find($user->tenant_id);

        if (!$tenant) {
            abort(404, 'Tenant not found.');
        }

        $request->attributes->set('tenant', $tenant);

        return $next($request);
    }
}