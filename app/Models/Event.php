<?php

namespace App\Models;

use App\Enums\EventTypes;
use App\Notifications\EventFinishedNotification;
use App\Notifications\EventNotification;
use App\Services\Translation\TranslationInterface;
use App\Support\Locale;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

class Event extends Model
{
    protected $fillable = [
        'service_center_id',
        'start',
        'finish',
        'total_addresses',
        'type',
        'effected_customers',
        'name',
        'name_en',
        'name_ru',
        'external_id',
        'planned',
        'finish_notified',
    ];

    protected $casts = [
        'start' => 'datetime',
        'finish' => 'datetime',
        'type' => EventTypes::class,
        'planned' => 'boolean',
        'finish_notified' => 'boolean',
    ];

    public function addresses(): BelongsToMany
    {
        return $this->belongsToMany(Address::class);
    }

    public function serviceCenter(): BelongsTo
    {
        return $this->belongsTo(ServiceCenter::class);
    }

    public function scopeCurrent(): Builder
    {
        return self::query()
            ->with('serviceCenter')
            ->where('finish', '>=', Carbon::now()->timezone('Asia/Tbilisi'))
            ->where('start', '<=', Carbon::now()->addWeek()->timezone('Asia/Tbilisi'))
            ->orderBy('start');
    }

    public static function getCurrent(EventTypes $type = null)
    {
        return self::query()
            ->current()
            ->when($type, function ($query) use ($type) {
                $query->where('type', $type->value);
            })
            ->get();
    }

    public function notifySubscribed(int $botUserId = null): int
    {
        $subscriptions = $this->subscriptionsToNotify($botUserId);

        $notifiedToday = Cache::get('notified_today', 0);
        foreach ($subscriptions as $subscription) {
            Notification::route('telegram', $subscription->bot_user_id)
                ->notify(new EventNotification(
                    $this,
                    $subscription->botUser->language_code,
                    $subscription->bot_user_id,
                    $subscription->street_filter,
                ));
            $notifiedToday++;
        }

        Cache::put('notified_today', $notifiedToday, now()->setTimezone('Asia/Tbilisi')->endOfDay());

        return count($subscriptions);
    }

    /**
     * Sent to the same subscribers as the start, unless the outage goes on:
     * water and energy sources report a postponed restore time as a new event.
     */
    public function notifySubscribedFinished(): int
    {
        $continuations = $this->continuations();

        $subscriptions = $this->subscriptionsToNotify()
            ->reject(fn(Subscriptions $subscription) => $continuations->contains(
                fn(Event $continuation) => $subscription->matches($continuation)
            ));

        foreach ($subscriptions as $subscription) {
            Notification::route('telegram', $subscription->bot_user_id)
                ->notify(new EventFinishedNotification(
                    $this,
                    $subscription->bot_user_id,
                    $subscription->street_filter,
                    $subscription->botUser->language_code,
                ));
        }

        return count($subscriptions);
    }

    private function subscriptionsToNotify(int $botUserId = null): Collection
    {
        $subscriptions = Subscriptions::query()
            ->with('botUser')
            ->when($botUserId, function ($query) use ($botUserId) {
                $query->where('bot_user_id', $botUserId);
            })
            ->where('service_center_id', $this->service_center_id)
            ->get();

        if ($subscriptions->contains(fn(Subscriptions $subscription) => $subscription->street_filter)) {
            $this->load('addresses');
            $subscriptions = $subscriptions->filter(fn(Subscriptions $subscription) => $subscription->matches($this));
        }

        return $subscriptions;
    }

    /**
     * Outages still on at the same addresses (or, for gas, under the same
     * title, as gas events come without addresses).
     */
    private function continuations(): Collection
    {
        $this->loadMissing('addresses');
        $addressIds = $this->addresses->modelKeys();

        return self::query()
            ->with('addresses')
            ->where('id', '!=', $this->id)
            ->where('service_center_id', $this->service_center_id)
            ->where('type', $this->type->value)
            ->where('start', '<=', now())
            ->where('finish', '>', now())
            ->when(
                $addressIds,
                fn(Builder $query) => $query->whereHas('addresses', fn(Builder $query) => $query->whereKey($addressIds)),
                fn(Builder $query) => $query->where('name', $this->name),
            )
            ->get();
    }

    /**
     * Translates the whole title in one request, from the English version when
     * the source provides one: it reads better than the word-by-word Georgian
     * dictionary in translate:all. A failure leaves name_ru empty, and readers
     * fall back to name_en.
     */
    public function translateName(): void
    {
        if ($this->name_ru !== null) {
            return;
        }

        [$text, $from] = $this->name_en ? [$this->name_en, 'en_GB'] : [$this->name, 'ka_GE'];

        if (!$text) {
            return;
        }

        try {
            $this->name_ru = app(TranslationInterface::class)->translate($text, $from, 'ru');
            $this->save();
        } catch (Throwable $e) {
            report($e);
        }
    }

    public function publishToFacebook(): void
    {
        if (!config('services.facebook.page_id') || !config('services.facebook.page_token')) {
            return;
        }

        if ($this->finish->isPast()) {
            return;
        }

        // Not dispatched right away: facebook:publish-pending posts the whole
        // backlog ordered by start, whatever order the loaders found it in.
        $this->forceFill(['facebook_pending' => true])->save();
    }

    public function localizedName(?string $languageCode): ?string
    {
        return match ($languageCode) {
            Locale::GEORGIAN => $this->name ?: $this->name_en,
            Locale::ENGLISH => $this->name_en ?: $this->name_ru ?? $this->name,
            default => $this->name_ru ?? $this->name_en,
        };
    }

    public function getKindAttribute(): ?string
    {
        return $this->kindIn('ru');
    }

    public function kindIn(string $locale): ?string
    {
        return match ($this->planned) {
            true => __('telegram.planned', locale: $locale),
            false => __('telegram.emergency', locale: $locale),
            null => null,
        };
    }

    public function getFromToAttribute(): string
    {
        return $this->fromToIn('ru');
    }

    public function fromToIn(string $locale): string
    {
        if ($this->start->format('d.m.Y') === $this->finish->format('d.m.Y')) {
            $hours = $this->start->format('H:i') . ' - ' . $this->finish->format('H:i');
            if (now()->format('d.m') === $this->start->format('d.m')) {
                return __('telegram.today', locale: $locale) . ' ' . $hours;
            }
            if (now()->addDay()->format('d.m') === $this->start->format('d.m')) {
                return __('telegram.tomorrow', locale: $locale) . ' ' . $hours;
            }
            if (now()->addDays(2)->format('d.m') === $this->start->format('d.m')) {
                return __('telegram.day_after_tomorrow', locale: $locale) . ' ' . $hours;
            }
            return $this->start->format('d.m H:i') . ' - ' . $this->finish->format('H:i');
        }

        if ($this->start->format('m.Y') === $this->finish->format('m.Y')) {
            return $this->start->format('d.m H:i') . ' - ' . $this->finish->format('d.m H:i');
        }

        return $this->start->format('d.m.Y H:i') . ' - ' . $this->finish->format('d.m.Y H:i');
    }

    /**
     * Like from_to, but without "Сегодня"/"Завтра": a Facebook post stays on
     * the page long after the day it was written.
     */
    public function getAbsoluteFromToAttribute(): string
    {
        if ($this->start->format('d.m.Y') === $this->finish->format('d.m.Y')) {
            return $this->start->format('d.m.Y H:i') . ' - ' . $this->finish->format('H:i');
        }

        return $this->start->format('d.m.Y H:i') . ' - ' . $this->finish->format('d.m.Y H:i');
    }
}
