<?php

namespace App\Http\Middleware;

use App\Support\NavigationStack;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RememberSafeUrl
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (
            $request->isMethod('GET')
            && $response->isSuccessful()
            && !$request->expectsJson()
            && !$request->is('storage/*')
        ) {
            NavigationStack::record($request->fullUrl());
        }

        return $response;
    }
}
