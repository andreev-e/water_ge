<?php

namespace App\Support;

use App\Enums\EventTypes;
use App\Models\Event;

class FacebookPost
{
    const SHOW_IN_POST = 15;

    /**
     * Facebook renders posts as plain text, so unlike the Telegram message
     * there is no HTML here. Hashtags make one shared page filterable by
     * service center and by utility type.
     */
    public static function text(Event $event): string
    {
        $serviceCenter = $event->serviceCenter;
        $serviceCenterName = $serviceCenter->name_ru ?? $serviceCenter->name_en ?? $serviceCenter->name;

        $lines = [
            '🚫' . $event->type->getIcon() . $serviceCenterName . ': ' . $event->from_to,
        ];

        if ($event->type === EventTypes::gas) {
            $lines[] = $event->name_ru ?? $event->name_en ?? $event->name;
        } elseif ($event->addresses->isNotEmpty()) {
            $lines[] = '';
            foreach ($event->addresses->slice(0, self::SHOW_IN_POST) as $address) {
                $lines[] = $address->translit;
            }
            if ($event->addresses->count() > self::SHOW_IN_POST) {
                $lines[] = '... всего адресов: ' . $event->addresses->count();
            }
        }

        $lines[] = '';
        $lines[] = implode(' ', array_filter([
            self::hashtag($serviceCenterName),
            self::typeHashtag($event->type),
            '#отключения',
        ]));

        return implode("\n", $lines);
    }

    public static function hashtag(?string $name): ?string
    {
        $tag = preg_replace('/[^\p{L}\p{N}_]+/u', '', (string) $name);

        return $tag === '' ? null : '#' . $tag;
    }

    private static function typeHashtag(EventTypes $type): string
    {
        return match ($type) {
            EventTypes::water => '#вода',
            EventTypes::gas => '#газ',
            EventTypes::energy => '#свет',
        };
    }
}
