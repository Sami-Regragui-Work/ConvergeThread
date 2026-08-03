<?php

namespace App\Support;

class NavigationStack
{
    private const SESSION_KEY = 'nav_stack';

    private const MAX_DEPTH = 20;

    public static function record(string $url): void
    {
        $url = self::normalize($url);

        /** @var list<string> $stack */
        $stack = session(self::SESSION_KEY, []);

        $existingIndex = array_search($url, $stack, true);

        if ($existingIndex !== false && $existingIndex < count($stack) - 1) {
            $stack = array_values(array_slice($stack, 0, $existingIndex + 1));
        } elseif ($stack === [] || end($stack) !== $url) {
            $stack[] = $url;

            if (count($stack) > self::MAX_DEPTH) {
                array_shift($stack);
            }
        }

        session([self::SESSION_KEY => $stack]);

        $parent = self::parentUrl($url);
        if ($parent) {
            session(['last_safe_url' => $parent]);
        }
    }

    public static function undoLast(string $url): void
    {
        $url = self::normalize($url);

        /** @var list<string> $stack */
        $stack = session(self::SESSION_KEY, []);

        if ($stack !== [] && end($stack) === $url) {
            array_pop($stack);
            session([self::SESSION_KEY => $stack]);
        }
    }

    public static function parentUrl(?string $current = null): ?string
    {
        $current = self::normalize($current ?? url()->current());

        /** @var list<string> $stack */
        $stack = session(self::SESSION_KEY, []);

        if (count($stack) < 2) {
            return null;
        }

        if (end($stack) === $current) {
            $parent = $stack[count($stack) - 2];

            return self::isAllowed($parent) ? $parent : null;
        }

        $index = array_search($current, $stack, true);

        if ($index !== false && $index > 0) {
            $parent = $stack[$index - 1];

            return self::isAllowed($parent) ? $parent : null;
        }

        return null;
    }

    private static function isAllowed(string $url): bool
    {
        return !str_contains($url, '/auth/');
    }

    private static function normalize(string $url): string
    {
        $parts = parse_url($url);

        if ($parts === false) {
            return $url;
        }

        $scheme = $parts['scheme'] ?? 'http';
        $host = $parts['host'] ?? 'localhost';
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $path = rtrim($parts['path'] ?? '/', '/') ?: '/';

        return "{$scheme}://{$host}{$port}{$path}";
    }
}
