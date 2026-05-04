<?php

namespace App\Http\Middleware;

use App\Models\Group;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class GroupMember
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response|JsonResponse
    {
        $member = $request->user();

        if (!$member) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $group = $request->route('group');

        if (!$group instanceof Group) {
            return $next($request);
        }

        $isMember = $group->activeMembers()
            ->where('users.id', $member->id)
            ->exists();

        if (!$isMember) {
            return response()->json([
                'message' => 'You are not a member of this group.',
            ], 403);
        }

        return $next($request);
    }
}
