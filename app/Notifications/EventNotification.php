<?php

namespace App\Notifications;

use App\Enums\EventTypes;
use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\Telegram\TelegramMessage;

class EventNotification extends Notification implements ShouldQueue
{
    use Queueable;

    const SHOW_IN_MESSAGE = 15;

    public function __construct(
        public Event $event,
        public ?string $languageCode,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['telegram'];
    }

    /**
     * @throws \JsonException
     */
    public function toTelegram($notifiable): TelegramMessage
    {
        $url = url('https://water.andreev-e.ru/event/' . $this->event->id);

        $message = TelegramMessage::create()
            ->options([
                'parse_mode' => 'html',
            ])
            ->content('🚫' . $this->event->type->getIcon() . $this->event->serviceCenter->name_ru . ': ')
            ->line($this->event->from_to);

        if ($this->event->type === EventTypes::gas) {
            $message->line($this->event->name_ru ?? $this->event->name_en);
        } else {
            if ($this->event->serviceCenter->total_addresses) {
                $percent = round($this->event->addresses->count() / $this->event->serviceCenter->total_addresses * 100);
                if ($percent < 1) {
                    $percent = '<1';
                } else {
                    $percent = '~' . $percent;
                }
                $message->line($percent . '% адресов отключено:');
            }
        }

        foreach ($this->event->addresses->slice(0, self::SHOW_IN_MESSAGE) as $address) {
            $message->line($address->translit);
        }

        if (count($this->event->addresses) > self::SHOW_IN_MESSAGE) {
            $message->line('...');
            $message->line('');
            $message->line(__('telegram.promo', [], 'ru'));
            $message->button('Смотреть все (' . count($this->event->addresses) . ')', $url);
        } else {
            $message->line('');
            $message->line(__('telegram.promo', [], 'ru'));
            $message->button('Смотреть на сайте', $url);
        }



        return $message;
    }
}
