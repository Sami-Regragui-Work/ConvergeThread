<?php

namespace App\Support;

use Illuminate\Support\Facades\Auth;

class BackNavigation
{
    /** @var list<string> */
    private const ROOT_ROUTES = [
        'groups.index',
        'owner.index',
        'tenant-roles.index',
        'merge-sessions.index',
    ];

    public static function shouldShow(): bool
    {
        if (!Auth::check()) {
            return false;
        }

        if (request()->is('auth/*', 'invitations/*')) {
            return false;
        }

        $routeName = request()->route()?->getName();

        return $routeName && !in_array($routeName, self::ROOT_ROUTES, true);
    }

    public static function url(): string
    {
        $parent = NavigationStack::parentUrl();

        if ($parent) {
            return $parent;
        }

        $user = Auth::user();

        return $user && $user->isOwner()
            ? route('owner.index')
            : route('groups.index');
    }
}
