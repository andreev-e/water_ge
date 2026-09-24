<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * When each loader last fetched and parsed its source successfully,
 * so a silently broken source (blocked host, changed markup) shows up on the site.
 */
class SourceStatus
{
    public const WATER = 'water';
    public const GWP = 'gwp';
    public const ENERGY = 'energy';
    public const GAS = 'gas';

    public const LABELS = [
        self::WATER => '💧 water.gov.ge',
        self::GWP => '💧 GWP (Тбилиси)',
        self::ENERGY => '⚡️ Energo-Pro',
        self::GAS => '🔥 mygas.ge',
    ];

    // Loaders run every 5 minutes, so a few missed runs in a row mean the source is down.
    public const STALE_AFTER_MINUTES = 15;

    public static function markUpdated(string $source): void
    {
        Cache::forever(self::key($source), Carbon::now()->timestamp);
    }

    /**
     * @return array<string, ?Carbon> source => last successful update
     */
    public static function all(): array
    {
        $result = [];

        foreach (array_keys(self::LABELS) as $source) {
            $timestamp = Cache::get(self::key($source));
            $result[$source] = $timestamp ? Carbon::createFromTimestamp($timestamp) : null;
        }

        return $result;
    }

    public static function isStale(?Carbon $updatedAt): bool
    {
        return !$updatedAt || $updatedAt->lt(Carbon::now()->subMinutes(self::STALE_AFTER_MINUTES));
    }

    private static function key(string $source): string
    {
        return 'source_updated_at:' . $source;
    }
}
