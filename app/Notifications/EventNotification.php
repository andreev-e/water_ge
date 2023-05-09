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
        return TelegramMessage::create()
            ->content("Новое событие: ")
            ->line($this->event->serviceCenter->name_ru)
            ->line($this->event->start . ' - ' . $this->event->finish);

//        $url = url('/invoice/');
//            ->button('View Invoice', $url)
//            ->button('Download Invoice', $url)
//            ->buttonWithCallback('Confirm', 'confirm_invoice');
    }
}
