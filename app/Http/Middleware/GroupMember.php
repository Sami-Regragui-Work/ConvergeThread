<?php

namespace App\Http\Middleware;

use App\Models\Group;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class GroupMember
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $member = $request->user();

        if (!$member) {
            return redirect()
                ->route('auth.login')
                ->withErrors([
                    'email' => 'Unauthenticated.',
                ]);
        }

        $group = $request->route('group');

        if (!$group instanceof Group) {
            return $next($request);
        }

        $isMember = $group->activeMembers()
            ->where('users.id', $member->id)
            ->exists();

        if (!$isMember) {
            abort(403, 'You are not a member of this group.');
        }

        return $next($request);
    }
}