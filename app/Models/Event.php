<?php

namespace App\Models;

use App\Notifications\EventNotification;
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
    ];

    protected static function boot(): void
    {
        self::created(static function(Event $event) {
            Notification::route('telegram', 411174495)
                ->notify(new EventNotification($event));
        });
        parent::boot();
    }

    public function addresses(): BelongsToMany
    {
        return $this->belongsToMany(Address::class);
    }

    public function serviceCenter(): BelongsTo
    {
        return $this->belongsTo(ServiceCenter::class);
    }
}
