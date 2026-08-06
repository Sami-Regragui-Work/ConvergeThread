<?php

namespace App\Support;

class MessageEncryption
{
    public const PREFIX = 'e2ee:v1:';

    public static function isEncrypted(?string $content): bool
    {
        return is_string($content) && str_starts_with($content, self::PREFIX);
    }
}
