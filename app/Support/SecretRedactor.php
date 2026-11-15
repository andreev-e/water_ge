<?php

namespace App\Support;

use Throwable;

/**
 * Hides secrets that end up in log lines. Guzzle puts the request URL into exception
 * messages, and the Telegram bot token is part of that URL (/bot{token}/sendMessage).
 */
class SecretRedactor
{
    public const MASK = '[redacted]';

    // Telegram bot token: "<bot id>:<secret>", the secret part is [A-Za-z0-9_-].
    private const TELEGRAM_TOKEN = '/\b\d{5,}:[A-Za-z0-9_-]{20,}/';

    public static function redact(string $text): string
    {
        $text = preg_replace(self::TELEGRAM_TOKEN, self::MASK, $text) ?? $text;

        $configured = (string) config('services.telegram-bot-api.token');
        if ($configured !== '') {
            $text = str_replace($configured, self::MASK, $text);
        }

        return $text;
    }

    /**
     * Redacts strings inside arrays and exceptions. Exceptions become strings,
     * because Monolog would format them later without going through this method.
     */
    public static function redactValue(mixed $value): mixed
    {
        if (is_string($value)) {
            return self::redact($value);
        }

        if ($value instanceof Throwable) {
            return self::redact((string) $value);
        }

        if (is_array($value)) {
            return array_map(self::redactValue(...), $value);
        }

        return $value;
    }
}
