<?php

namespace App\Notifications;

use App\Enums\EventTypes;
use App\Models\Event;
use App\Models\Mail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\Telegram\TelegramMessage;

class MailNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Mail $mail,
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
        return TelegramMessage::create()->content($this->mail->text);
    }
}
