<?php

namespace App\Models;

use App\Enums\EventTypes;
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
        'total_addresses',
        'type',
        'effected_customers',
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

    public static function getCurrent(EventTypes $type = null)
    {
        return self::query()
            ->when($type, function($query) use ($type) {
                $query->where('type', $type->value);
            })
            ->orderBy('start')
            ->where('finish', '>=', Carbon::now()->timezone('Asia/Tbilisi'))
            ->where('start', '<=', Carbon::now()->addDay()->timezone('Asia/Tbilisi'))
            ->get();
    }
}
