<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceCenter extends Model
{

    protected $fillable = [
        'name',
        'name_en',
        'name_ru',
    ];
    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class)
            ->orderBy('total_events', 'DESC');
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }
}
