<?php

namespace App\Notifications;

use App\Enums\EventTypes;
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
        $url = url('https://water.andreev-e.ru/?service_center_id=' . $this->event->service_center_id . '#' . $this->event->id);

        $message = TelegramMessage::create()
            ->content('🚫' . $this->event->type->getIcon() . $this->event->serviceCenter->name_ru . ': ')
            ->line($this->event->start->format('d.m.Y H:i') . ' - ' . $this->event->finish->format('d.m.Y H:i'));

        if ($this->event->type === EventTypes::gas) {
            $message->line($this->event->name_ru);
        } else {
            if ($this->event->serviceCenter->total_addresses) {
                $message->line('~' . round($this->event->addresses->count() / $this->event->serviceCenter->total_addresses * 100) . '% адресов отключено:');
            }
        }

        foreach ($this->event->addresses->slice(0, 5) as $address) {
            $message->line($address->name_ru . ' (' . $address->name . ')');
        }

        if (count($this->event->addresses) > 5) {
            $message->line('...');
            $message->button('Смотреть все (' . count($this->event->addresses) . ')', $url);
        }

        return $message;

//            ->buttonWithCallback('Confirm', 'confirm_invoice');
    }
}
