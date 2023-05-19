<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Event extends Model
{
    protected $fillable = [
        'service_center_id',
        'start',
        'finish',
    ];

    protected $casts = [
        'start' => 'datetime',
        'finish' => 'datetime',
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
