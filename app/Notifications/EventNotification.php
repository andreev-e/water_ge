<?php

namespace App\Notifications;

use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\Telegram\TelegramMessage;

class EventNotification extends Notification
{
    use Queueable;

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
        $url = url('https://water.andreev-e.ru/');

        $message = TelegramMessage::create()
            ->content(__('telegram.water_shutdown', locale: $this->languageCode) . ': ')
            ->line($this->event->serviceCenter->name_ru)
            ->line($this->event->start->format('d.m.Y H:i') . ' - ' . $this->event->finish->format('d.m.Y H:i'))
            ->line('~' . round($this->event->addresses->count() / $this->event->serviceCenter->total_addresses * 100) . '% адресов отключено:');

        foreach ($this->event->addresses as $address) {
            $message->line($address->name_ru . ' (' . $address->name . ')');
        }

        $message->button('Смотреть все', $url);

        return $message;

//            ->buttonWithCallback('Confirm', 'confirm_invoice');
    }
}
