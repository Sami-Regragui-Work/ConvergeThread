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
        $tracked = $this->shouldTrack($request);
        $pageUrl = $request->url();

        if ($tracked) {
            NavigationStack::record($pageUrl);
        }

        $response = $next($request);

        if ($tracked && !$response->isSuccessful()) {
            NavigationStack::undoLast($pageUrl);
        }

        return $response;
    }

    private function shouldTrack(Request $request): bool
    {
        return $request->isMethod('GET')
            && !$request->expectsJson()
            && !$request->is('storage/*')
            && !$request->routeIs(
                'messages.attachment',
                'messages.attachments.download',
            );
    }
}
