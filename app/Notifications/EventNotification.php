<?php

namespace App\Notifications;

use App\Enums\EventTypes;
use App\Models\Address;
use App\Models\Event;
use App\Models\Subscriptions;
use App\Support\Locale;
use App\Support\TelegramFailure;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use NotificationChannels\Telegram\Exceptions\CouldNotSendNotification;
use NotificationChannels\Telegram\TelegramMessage;
use Throwable;

class EventNotification extends Notification implements ShouldQueue
{
    use Queueable;

    const SHOW_IN_MESSAGE = 15;

    public function __construct(
        public Event $event,
        public ?string $languageCode,
        public int $botUserId,
        public ?string $streetFilter = null,
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
        $this->hydrateEventRelations();

        $locale = Locale::notification($this->languageCode);
        $url = url('https://water.andreev-e.ru' . Locale::webPrefix($locale) . '/event/' . $this->event->id);
        $kind = $this->event->kindIn($locale);

        $message = TelegramMessage::create()
            ->options([
                'parse_mode' => 'html',
                'disable_web_page_preview' => true,
            ])
            ->content('🚫<b>' . $this->event->type->getIcon() . $this->event->serviceCenter->localizedName($locale) . '</b>: ')
            ->line('<b>' . $this->event->fromToIn($locale) . '</b>' . ($kind ? ' (' . mb_strtolower($kind) . ')' : ''));

        if ($this->event->type === EventTypes::gas) {
            $message->line($this->event->localizedName($locale));
        } else {
            if ($this->event->serviceCenter->total_addresses) {
                $percent = round($this->event->addresses->count() / $this->event->serviceCenter->total_addresses * 100);
                if ($percent < 1) {
                    $percent = '&lt;1';
                } else {
                    $percent = '~' . $percent;
                }
                $message->line(__('telegram.addresses_off', ['percent' => $percent], $locale));
            }
        }

        foreach ($this->addressesToShow()->slice(0, self::SHOW_IN_MESSAGE) as $address) {
            $message->line($address->localizedName($locale));
        }

        if (count($this->event->addresses) > self::SHOW_IN_MESSAGE) {
            $message->line('...');
            $message->line('');
            $message->line(__('telegram.promo', [], $locale));
            $message->button(__('telegram.all_addresses', ['count' => count($this->event->addresses)], $locale), $url);
        } else {
            $message->line('');
            $message->line(__('telegram.promo', [], $locale));
        }

        return $message;
    }

    /**
     * With a street filter the subscriber's streets go first, otherwise they
     * may be hidden behind the first SHOW_IN_MESSAGE addresses.
     */
    private function addressesToShow(): Collection
    {
        // ?? keeps notifications queued before streetFilter existed working.
        $terms = Subscriptions::parseStreetFilter($this->streetFilter ?? null);
        if (!$terms) {
            return $this->event->addresses;
        }

        return $this->event->addresses
            ->sortByDesc(fn(Address $address) => Subscriptions::containsAny($address->name . ' ' . $address->translit, $terms))
            ->values();
    }

    /**
     * The model is re-fetched from scratch when this queued notification is
     * unserialized (relations loaded before dispatch don't survive the
     * queue round-trip), so without this every subscriber's job would repeat
     * the same serviceCenter/addresses queries for the same event.
     */
    private function hydrateEventRelations(): void
    {
        $relations = Cache::remember('event_notification_relations_' . $this->event->id, 3600, function () {
            $this->event->loadMissing(['serviceCenter', 'addresses']);

            return [
                'serviceCenter' => $this->event->serviceCenter,
                'addresses' => $this->event->addresses,
            ];
        });

        $this->event->setRelation('serviceCenter', $relations['serviceCenter']);
        $this->event->setRelation('addresses', $relations['addresses']);
    }

    public function failed(Throwable $exception): void
    {
        if ($exception instanceof CouldNotSendNotification && TelegramFailure::isKnown($exception->getMessage())) {
            Subscriptions::query()
                ->where('bot_user_id', $this->botUserId ?? null)
                ->delete();

            return;
        }

        report($exception);
    }
}
