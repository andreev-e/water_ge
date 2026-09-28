<?php

namespace App\Models;

use App\Enums\EventTypes;
use App\Jobs\PublishEventToFacebook;
use App\Notifications\EventNotification;
use App\Services\Translation\TranslationInterface;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
    ];

    protected $casts = [
        'start' => 'datetime',
        'finish' => 'datetime',
        'type' => EventTypes::class,
        'planned' => 'boolean',
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

        PublishEventToFacebook::dispatch($this);
    }

    public function getKindAttribute(): ?string
    {
        return match ($this->planned) {
            true => 'Плановое',
            false => 'Аварийное',
            null => null,
        };
    }

    public function getFromToAttribute(): string
    {
        if ($this->start->format('d.m.Y') === $this->finish->format('d.m.Y')) {
            if (now()->format('d.m') === $this->start->format('d.m')) {
                return 'Сегодня ' . $this->start->format('H:i') . ' - ' . $this->finish->format('H:i');
            }
            if (now()->addDay()->format('d.m') === $this->start->format('d.m')) {
                return 'Завтра ' . $this->start->format('H:i') . ' - ' . $this->finish->format('H:i');
            }
            if (now()->addDays(2)->format('d.m') === $this->start->format('d.m')) {
                return 'Послезавтра ' . $this->start->format('H:i') . ' - ' . $this->finish->format('H:i');
            }
            return $this->start->format('d.m H:i') . ' - ' . $this->finish->format('H:i');
        }

        if ($this->start->format('m.Y') === $this->finish->format('m.Y')) {
            return $this->start->format('d.m H:i') . ' - ' . $this->finish->format('d.m H:i');
        }

        return $this->start->format('d.m.Y H:i') . ' - ' . $this->finish->format('d.m.Y H:i');
    }
}
