<?php

namespace App\Notifications;

use App\Models\Address;
use App\Models\Event;
use App\Models\Subscriptions;
use App\Support\Locale;
use App\Support\TelegramFailure;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\Telegram\Exceptions\CouldNotSendNotification;
use NotificationChannels\Telegram\TelegramMessage;
use Throwable;

class EventFinishedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    const SHOW_IN_MESSAGE = 15;

    public function __construct(
        public Event $event,
        public int $botUserId,
        public ?string $streetFilter = null,
        public ?string $languageCode = null,
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
        $this->event->loadMissing(['serviceCenter', 'addresses']);
        // ?? keeps notifications queued before languageCode existed working.
        $locale = Locale::notification($this->languageCode ?? null);
        $kind = $this->event->kindIn($locale);

        $message = TelegramMessage::create()
            ->options([
                'parse_mode' => 'html',
                'disable_web_page_preview' => true,
            ])
            ->content('✅<b>' . $this->event->type->getIcon() . $this->event->serviceCenter->localizedName($locale) . '</b>: ')
            ->line(__('telegram.finished', [], $locale))
            ->line('<b>' . $this->event->absolute_from_to . '</b>' . ($kind ? ' (' . mb_strtolower($kind) . ')' : ''));

        // The whole list was in the first message; repeat only the subscriber's own streets.
        $terms = Subscriptions::parseStreetFilter($this->streetFilter);
        if ($terms) {
            $addresses = $this->event->addresses
                ->filter(fn(Address $address) => Subscriptions::containsAny($address->name . ' ' . $address->translit, $terms));
            foreach ($addresses->slice(0, self::SHOW_IN_MESSAGE) as $address) {
                $message->line($address->localizedName($locale));
            }
            if ($addresses->count() > self::SHOW_IN_MESSAGE) {
                $message->line('...');
            }
        }

        return $message
            ->line('')
            ->line(__('telegram.donate_ask', [], $locale))
            ->button(__('telegram.details', [], $locale), url('https://water.andreev-e.ru' . Locale::webPrefix(Locale::web($this->languageCode ?? null)) . '/event/' . $this->event->id))
            ->buttonWithCallback(__('telegram.donate_button', [], $locale), 'command=donate');
    }

    public function failed(Throwable $exception): void
    {
        if ($exception instanceof CouldNotSendNotification && TelegramFailure::isKnown($exception->getMessage())) {
            Subscriptions::query()
                ->where('bot_user_id', $this->botUserId)
                ->delete();

            return;
        }

        report($exception);
    }
}
