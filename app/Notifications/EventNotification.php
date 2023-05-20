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
        $url = url('https://water.andreev-e.ru/#' . $this->event->id);

        $message = TelegramMessage::create()
            ->content($this->event->type->getIcon() . __('telegram.shutdown', locale: $this->languageCode) . ': ')
            ->line($this->event->serviceCenter->name_ru)
            ->line($this->event->start->format('d.m.Y H:i') . ' - ' . $this->event->finish->format('d.m.Y H:i'))
            ->line('~' . round($this->event->addresses->count() / $this->event->serviceCenter->total_addresses * 100) . '% адресов отключено, ' . count($this->event->addresses) . ':');

        foreach ($this->event->addresses->slice(0, 5) as $address) {
            $message->line($address->name_ru . ' (' . $address->name . ')');
        }

        if ($this->event->addresses->count() > 5) {
            $message->button('Смотреть все ' . count($this->event->addresses), $url);
        }


        return $message;

//            ->buttonWithCallback('Confirm', 'confirm_invoice');
    }
}
