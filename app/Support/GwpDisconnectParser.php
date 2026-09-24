<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * GWP (Tbilisi water) exposes dateFrom/dateTo as zeroes, so the real period
 * and the affected addresses exist only inside the free-form Georgian text.
 * Seen formats:
 *  - "9/24/2026 11:45 დან 9/24/2026 23:30 საათამდე" (M/D/Y)
 *  - "9/24/2026 23:00 საათამდე" (only the restore time)
 *  - "აღდგება 09/24 17:30 საათამდე" (M/D, no year)
 *  - "26/09 03:00 სთ-დან 27/09 15:00 სთ-მდე" (D/M, no year)
 */
class GwpDisconnectParser
{
    private const DATE_PATTERN = '~(\d{1,2})/(\d{1,2})(?:/(\d{4}))?\s+(\d{1,2}):(\d{2})\s*(სთ-დან|დან|საათამდე|სთ-მდე|მდე)?~u';

    private const ADDRESS_MARKERS = ['შეუწყდება:', 'შეუწყდა:', 'შეწყვეტილი აქვს:', 'სთ-მდე :', 'სთ-მდე:'];

    /**
     * @return array{0: ?Carbon, 1: ?Carbon} [start, finish]
     */
    public static function parsePeriod(string $text, ?Carbon $now = null): array
    {
        $now ??= Carbon::now();
        $start = null;
        $finish = null;

        preg_match_all(self::DATE_PATTERN, $text, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $date = self::buildDate((int) $match[1], (int) $match[2], $match[3] ?? '', (int) $match[4], (int) $match[5], $now);

            if (!$date) {
                continue;
            }

            $suffix = $match[6] ?? '';

            if (str_ends_with($suffix, 'დან') && !$start) {
                $start = $date;
            } elseif (str_ends_with($suffix, 'მდე') || $start) {
                $finish = $date;
            } else {
                $start = $date;
            }
        }

        return [$start, $finish];
    }

    /**
     * @return string[]
     */
    public static function parseAddresses(string $text): array
    {
        $position = false;

        foreach (self::ADDRESS_MARKERS as $marker) {
            $found = mb_strpos($text, $marker);

            if ($found !== false && ($position === false || $found < $position[0])) {
                $position = [$found, mb_strlen($marker)];
            }
        }

        if ($position === false) {
            return [];
        }

        $list = mb_substr($text, $position[0] + $position[1]);
        $addresses = [];

        foreach (preg_split('~[,;\n]~u', $list) as $part) {
            // Drop group headers like "ვაკის რაიონი:" or "სოფელი დიღომში:".
            if (mb_strpos($part, ':') !== false) {
                $part = mb_substr($part, mb_strrpos($part, ':') + 1);
            }

            $part = trim(preg_replace('~\s+~u', ' ', $part), " .\t\r");

            if ($part !== '' && mb_strlen($part) <= 255) {
                $addresses[$part] = true;
            }
        }

        return array_keys($addresses);
    }

    /**
     * The day/month order is not consistent between messages, so pick the
     * valid interpretation that lands closest to now.
     */
    private static function buildDate(int $a, int $b, string $year, int $hour, int $minute, Carbon $now): ?Carbon
    {
        $years = $year !== '' ? [(int) $year] : [$now->year - 1, $now->year, $now->year + 1];
        $best = null;

        foreach ($years as $y) {
            foreach ([[$a, $b], [$b, $a]] as [$month, $day]) {
                if (!checkdate($month, $day, $y) || $hour > 23 || $minute > 59) {
                    continue;
                }

                $candidate = Carbon::create($y, $month, $day, $hour, $minute, 0, $now->getTimezone());

                if (!$best || abs($candidate->diffInSeconds($now, false)) < abs($best->diffInSeconds($now, false))) {
                    $best = $candidate;
                }
            }
        }

        return $best;
    }
}
