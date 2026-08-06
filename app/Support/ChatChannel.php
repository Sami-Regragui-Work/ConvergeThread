<?php

namespace App\Support;

final class ChatChannel
{
    public static function name(string $chatType, int $chatId): string
    {
        return "chat.{$chatType}.{$chatId}";
    }
}
