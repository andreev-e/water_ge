<?php

namespace App\Models;

use App\Support\Locale;
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

    public function localizedName(?string $languageCode): string
    {
        return match ($languageCode) {
            Locale::GEORGIAN => $this->name,
            Locale::ENGLISH => $this->name_en ?: $this->name,
            default => $this->name_ru ?: $this->name,
        };
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class)
            ->orderBy('total_events', 'DESC');
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscriptions::class);
    }
}
