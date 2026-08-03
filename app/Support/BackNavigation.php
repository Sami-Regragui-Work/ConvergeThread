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
        $current = url()->current();

        $candidates = array_values(array_filter([
            self::safeCandidate(session('last_safe_url'), $current),
            self::safeCandidate(url()->previous(), $current),
        ]));

        if ($candidates !== []) {
            return $candidates[0];
        }

        $user = Auth::user();

        return $user && $user->isOwner()
            ? route('owner.index')
            : route('groups.index');
    }

    private static function safeCandidate(?string $candidate, string $current): ?string
    {
        if (!$candidate || $candidate === $current) {
            return null;
        }

        if (str_contains($candidate, '/auth/')) {
            return null;
        }

        return $candidate;
    }
}
