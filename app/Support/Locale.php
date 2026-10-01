<?php

namespace App\Support;

use Illuminate\Support\Facades\App;

/**
 * The sources publish everything in Georgian, so Georgian users get the
 * original names; everyone else keeps getting the Russian translations.
 */
class Locale
{
    public const GEORGIAN = 'ka';
    public const ENGLISH = 'en';

    /**
     * The site's languages. Russian keeps the unprefixed URLs it was indexed
     * under, the others live under /en and /ka.
     */
    public const WEB = ['ru', self::ENGLISH, self::GEORGIAN];
    public const WEB_DEFAULT = 'ru';

    public const WEB_NAMES = [
        'ru' => 'Русский',
        self::ENGLISH => 'English',
        self::GEORGIAN => 'ქართული',
    ];

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

    /**
     * The site language for a Telegram user: their own one when the site has it
     * ('en', 'en-us' → 'en'), Russian otherwise.
     */
    public static function web(?string $languageCode): string
    {
        $language = strtolower(strtok((string)$languageCode, '-_'));

        return in_array($language, self::WEB, true) ? $language : self::WEB_DEFAULT;
    }

    /**
     * Path prefix of the site in that language: '' for Russian, '/ka' for Georgian.
     */
    public static function webPrefix(string $locale): string
    {
        return $locale === self::WEB_DEFAULT ? '' : '/' . $locale;
    }

    /**
     * Each language has its own copy of the routes: 'event' in Russian, 'ka.event' in Georgian.
     */
    public static function routeName(string $name, ?string $locale = null): string
    {
        $locale ??= App::getLocale();

        return $locale === self::WEB_DEFAULT ? $name : $locale . '.' . $name;
    }

    public static function route(string $name, mixed $parameters = [], ?string $locale = null): string
    {
        return route(self::routeName($name, $locale), $parameters);
    }

    /**
     * The current page in another language, query string included.
     */
    public static function switchUrl(string $locale): string
    {
        $route = request()->route();
        $name = preg_replace('/^(' . implode('|', self::WEB) . ')\./', '', (string)$route?->getName());

        if (!$name) {
            return url(self::webPrefix($locale) ?: '/');
        }

        return self::route($name, $route->parameters() + request()->except('live'), $locale);
    }
}
