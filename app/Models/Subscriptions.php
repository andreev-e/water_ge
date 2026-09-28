<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscriptions extends Model
{
    protected $fillable = [
        'bot_user_id',
        'service_center_id',
        'street_filter',
    ];

    public function botUser(): BelongsTo
    {
        return $this->belongsTo(BotUser::class);
    }

    public function serviceCenter(): BelongsTo
    {
        return $this->belongsTo(ServiceCenter::class);
    }

    /**
     * @return string[] lowercased street names from the filter, empty when there is none
     */
    public static function parseStreetFilter(?string $filter): array
    {
        return collect(explode(',', (string)$filter))
            ->map(fn(string $term) => mb_strtolower(trim($term)))
            ->filter(fn(string $term) => $term !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Addresses are stored in Georgian and shown transliterated, so a term
     * matches either spelling. Gas events come without addresses: their title
     * lists the streets instead.
     */
    public function matches(Event $event): bool
    {
        $terms = self::parseStreetFilter($this->street_filter);
        if (!$terms) {
            return true;
        }

        $haystacks = $event->addresses->isNotEmpty()
            ? $event->addresses->flatMap(fn(Address $address) => [$address->name, $address->translit])
            : collect([$event->name, $event->name_en, $event->name_ru]);
        $haystacks = $haystacks->filter();

        // Nothing to match against: better an extra message than a missed outage.
        if ($haystacks->isEmpty()) {
            return true;
        }

        return $haystacks->contains(fn(string $haystack) => self::containsAny($haystack, $terms));
    }

    public static function containsAny(string $haystack, array $terms): bool
    {
        $haystack = mb_strtolower($haystack);
        foreach ($terms as $term) {
            if (str_contains($haystack, $term)) {
                return true;
            }
        }

        return false;
    }
}
