<?php

namespace App\Support;

use Illuminate\Http\RedirectResponse;

final class Flash
{
    /**
     * @param  array<int, array{label: string, url: string}>  $links
     */
    public static function back(string $message, array $links = [], string $type = 'success'): RedirectResponse
    {
        $redirect = back()->with($type, $message);

        if ($links !== []) {
            $redirect->with('flash_links', $links);
        }

        return $redirect;
    }

    /**
     * @param  array<int, array{label: string, url: string}>  $links
     */
    public static function to(
        string $route,
        string $message,
        array $links = [],
        mixed $parameters = [],
        string $type = 'success',
    ): RedirectResponse {
        $redirect = redirect()->route($route, $parameters)->with($type, $message);

        if ($links !== []) {
            $redirect->with('flash_links', $links);
        }

        return $redirect;
    }
}
