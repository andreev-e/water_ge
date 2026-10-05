<?php

namespace App\Models;

use App\Support\Locale;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ServiceCenter extends Model
{

    protected $fillable = [
        'name',
        'name_en',
        'name_ru',
        'slug',
    ];

    /**
     * Russian names spelled the way Georgian places are written in Latin:
     * Боржоми → borjomi, Цхалтубо → tskhaltubo.
     */
    private const TRANSLITERATION = [
        'дж' => 'j', 'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'yo',
        'ж' => 'j', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n',
        'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'kh',
        'ц' => 'ts', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'shch', 'ъ' => '', 'ы' => 'y', 'ь' => '', 'э' => 'e',
        'ю' => 'yu', 'я' => 'ya', '/' => ' ',
    ];

    /**
     * New centers come with the Georgian name only; the slug is made once
     * Translate adds the Russian one, and stays put after that so links don't break.
     * Until then the page is reachable by id.
     */
    protected static function booted(): void
    {
        static::saving(function (ServiceCenter $serviceCenter) {
            if (!$serviceCenter->slug && $serviceCenter->name_ru) {
                $serviceCenter->slug = self::uniqueSlug($serviceCenter->name_ru);
            }
        });
    }

    public static function uniqueSlug(string $name): string
    {
        $base = Str::slug(strtr(mb_strtolower($name), self::TRANSLITERATION)) ?: 'service-center';
        $slug = $base;
        for ($i = 2; static::query()->where('slug', $slug)->exists(); $i++) {
            $slug = $base . '-' . $i;
        }

        return $slug;
    }

    public function getRouteKey(): string
    {
        return $this->slug ?? (string)$this->id;
    }

    /**
     * Takes the id as well: centers without a slug yet, and old links,
     * which the controller redirects to the slug.
     */
    public function resolveRouteBinding($value, $field = null): ?self
    {
        return static::query()
            ->where('slug', $value)
            ->when(ctype_digit((string)$value), fn($query) => $query->orWhere('id', $value))
            ->first();
    }

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
