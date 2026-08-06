<?php

namespace App\Support;

class DisplayName
{
    /**
     * Capitalize only the first character; leave the rest of the string unchanged.
     */
    public static function capitalizeFirst(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        $first = mb_strtoupper(mb_substr($value, 0, 1));
        $rest = mb_substr($value, 1);

        return $first.$rest;
    }
}
