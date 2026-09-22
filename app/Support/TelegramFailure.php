<?php

namespace App\Support;

class TelegramFailure
{
    private const KNOWN_MESSAGES = [
        'bot was blocked by the user',
        'chat not found',
        'user is deactivated',
        'user_bot_to_bot_disabled',
        'send messages to the bot',
        'send messages to bots',
    ];

    public static function isKnown(?string $message): bool
    {
        $message = mb_strtolower((string) $message);

        foreach (self::KNOWN_MESSAGES as $known) {
            if (str_contains($message, $known)) {
                return true;
            }
        }

        return false;
    }
}
