<?php

namespace App\Models;

use App\Enums\EventTypes;
use App\Notifications\EventNotification;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Notification;

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
    ];

    protected $casts = [
        'start' => 'datetime',
        'finish' => 'datetime',
        'type' => EventTypes::class,
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
            ->with(['serviceCenter', 'addresses'])
            ->where('finish', '>=', Carbon::now()->timezone('Asia/Tbilisi'))
            ->where('start', '<=', Carbon::now()->addWeek()->timezone('Asia/Tbilisi'))
            ->orderBy('start');
    }

    public static function getCurrent(EventTypes $type = null)
    {
        return self::query()
            ->current()
            ->when($type, function($query) use ($type) {
                $query->where('type', $type->value);
            })
            ->get();
    }

    public function notifySubscribed(int $botUserId = null): int
    {
        $subscriptions = Subscriptions::query()
            ->with('botUser')
            ->when($botUserId, function($query) use ($botUserId) {
                $query->where('bot_user_id', $botUserId);
            })
            ->where('service_center_id', $this->service_center_id)
            ->get();

        foreach ($subscriptions as $subscription) {
            Notification::route('telegram', $subscription->bot_user_id)
                ->notify(new EventNotification($this, $subscription->botUser->language_code));
        }

        return count($subscriptions);
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
