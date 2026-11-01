<?php

namespace App\Models;

use App\Services\Publications\DigestPeriod;
use App\Support\Locale;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Публикация сайта — сводка отключений за неделю или месяц: цифры периода (statistics)
 * и текст о них на языках сайта. Опубликованную видят все, черновик — только тот,
 * у кого есть ссылка из админки.
 * Адрес страницы — с языком в пути, как у остальных страниц; слаг один на все языки.
 */
class Publication extends Model
{
    public const TYPE_WEEK = 'week';

    public const TYPE_MONTH = 'month';

    public const TYPES = [self::TYPE_WEEK, self::TYPE_MONTH];

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    /** Язык текста, если для языка страницы перевода нет: русский есть всегда, его пишет команда первой. */
    public const FALLBACK_LOCALE = 'ru';

    protected $guarded = ['id'];

    protected $casts = [
        'period_start' => 'date:Y-m-d',
        'period_end' => 'date:Y-m-d',
        'published_at' => 'datetime',
        'generated_at' => 'datetime',
        'statistics' => 'array',
    ];

    /** @return HasMany<PublicationTranslation, $this> */
    public function translations(): HasMany
    {
        return $this->hasMany(PublicationTranslation::class);
    }

    /**
     * Опубликованное — то, что видят читатели и поисковики.
     *
     * @param  Builder<Publication>  $query
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED)->whereNotNull('published_at');
    }

    /** Текст на языке страницы; перевода нет — русский. */
    public function translation(string $locale): ?PublicationTranslation
    {
        return $this->translations->firstWhere('locale', $locale)
            ?? $this->translations->firstWhere('locale', self::FALLBACK_LOCALE);
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED && $this->published_at !== null;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** Период словами: «26.10–01.11.2026» для недели, «Октябрь 2026» для месяца. */
    public function periodLabel(string $locale): string
    {
        if (!$this->period_start || !$this->period_end) {
            return '';
        }

        return DigestPeriod::label([
            'type' => $this->type,
            'start' => $this->period_start->toDateString(),
            'end' => $this->period_end->toDateString(),
        ], $locale);
    }

    /** Адрес страницы на языке: «/publications/week-2026-10-26», «/ka/publications/…». */
    public function url(?string $locale = null): string
    {
        return Locale::route('publication', ['publication' => $this], $locale);
    }
}
