<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Текст публикации на одном языке: заголовок, краткое описание для списка и полный текст страницы.
 * Тело хранится абзацами через пустую строку; абзац из строк «- …» — список.
 */
class PublicationTranslation extends Model
{
    protected $guarded = ['id'];

    /** @return BelongsTo<Publication, $this> */
    public function publication(): BelongsTo
    {
        return $this->belongsTo(Publication::class);
    }

    /**
     * Абзацы тела для страницы: текст или список.
     *
     * @return array<int, array{list: bool, text: string, lines: string[]}>
     */
    public function blocks(): array
    {
        $paragraphs = preg_split('/\R{2,}/u', (string) $this->body) ?: [];

        return array_values(array_filter(array_map(function (string $paragraph): array {
            $lines = array_values(array_filter(array_map('trim', preg_split('/\R/u', trim($paragraph)) ?: [])));
            $list = $lines !== [] && str_starts_with($lines[0], '- ');

            return [
                'list' => $list,
                'lines' => $list ? array_map(fn (string $line) => ltrim(substr($line, 1)), $lines) : [],
                'text' => $list ? '' : implode(' ', $lines),
            ];
        }, $paragraphs), fn (array $block) => $block['list'] ? $block['lines'] !== [] : $block['text'] !== ''));
    }
}
