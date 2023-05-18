<?php

namespace App\Models;

use App\Notifications\EventNotification;
use Carbon\Carbon;
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

    public function addresses(): BelongsToMany
    {
        return $this->belongsToMany(Address::class);
    }

    public function serviceCenter(): BelongsTo
    {
        return $this->belongsTo(ServiceCenter::class);
    }


    public static function getCurrent()
    {
        return self::query()
            ->where('finish', '>=', Carbon::now())
            ->get();
    }
}
