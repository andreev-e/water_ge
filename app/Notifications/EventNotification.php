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
        public Event $event
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
            ->content("Новое событие: ")
            ->line($this->event->serviceCenter->name_ru)
            ->line($this->event->start . ' - ' . $this->event->finish)
            ->line(round($this->event->addresses->count() / $this->event->serviceCenter->total_addresses * 100) . '%');

        foreach ($this->event->addresses as $address) {
            $message->line($address->name_ru);
        }

        $message->button('Смотреть все', $url);

        return $message;

//            ->buttonWithCallback('Confirm', 'confirm_invoice');
    }
}
