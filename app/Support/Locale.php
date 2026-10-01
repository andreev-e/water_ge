<?php

namespace App\Support;

/**
 * The sources publish everything in Georgian, so Georgian users get the
 * original names; everyone else keeps getting the Russian translations.
 */
class Locale
{
    public const GEORGIAN = 'ka';

    public static function isGeorgian(?string $languageCode): bool
    {
        return $languageCode === self::GEORGIAN;
    }

    /**
     * Notifications were written for Russian speakers before the bot had
     * localized UI, so only Georgian is split off from them.
     */
    public static function notification(?string $languageCode): string
    {
        return self::isGeorgian($languageCode) ? self::GEORGIAN : 'ru';
    }
}
