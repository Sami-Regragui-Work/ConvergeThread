<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BanCheck
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response|JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        if ($user->banned_by_id !== null) {
            return response()->json([
                'message' => 'Your account is banned.',
            ], 403);
        }

        return $next($request);
    }
}
