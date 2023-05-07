<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Address extends Model
{
    protected $fillable = [
        'name',
        'name_en',
        'name_ru',
    ];


    public function events(): BelongsToMany
    {
        return $this->belongsToMany(Event::class);
    }
}
