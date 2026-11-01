<?php

namespace Tests\Concerns;

use App\Models\Publication;

/**
 * Публикация для тестов страниц: сводка за неделю 26.10–01.11.2026 с текстом
 * на трёх языках. Тексты помечены языком, чтобы тест видел, какой из них отрисован.
 */
trait CreatesPublications
{
    use BuildsDigestStatistics;

    /**
     * @param  array<string, mixed>  $overrides
     * @param  array<string, array{title: string, excerpt: string, body: string}>  $texts
     */
    protected function publication(array $overrides = [], array $texts = []): Publication
    {
        $publication = Publication::create($overrides + [
            'slug' => 'week-2026-10-26',
            'type' => Publication::TYPE_WEEK,
            'period_start' => '2026-10-26',
            'period_end' => '2026-11-01',
            'status' => Publication::STATUS_PUBLISHED,
            'published_at' => '2026-11-02 09:00:00',
            'statistics' => $this->digestStatistics(),
            'generated_at' => '2026-11-02 08:00:00',
        ]);

        foreach ($texts ?: $this->digestTexts() as $locale => $text) {
            $publication->translations()->create(['locale' => $locale] + $text);
        }

        return $publication->load('translations');
    }

    /**
     * @return array<string, array{title: string, excerpt: string, body: string}>
     */
    protected function digestTexts(): array
    {
        return [
            'ru' => [
                'title' => 'Сводка за неделю 26.10–01.11.2026',
                'excerpt' => 'Отключений: 3 (+50% к прошлому периоду). Чаще всего — Тбилиси.',
                'body' => "Всего отключений: 3; к прошлому периоду — +50%.\n\n- Вода — 2\n- Электричество — 1",
            ],
            'en' => [
                'title' => 'Weekly digest 26.10–01.11.2026',
                'excerpt' => 'Outages: 3 (+50% against the previous period). Most often — Tbilisi.',
                'body' => "Outages in total: 3; against the previous period — +50%.\n\n- Water — 2\n- Electricity — 1",
            ],
            'ka' => [
                'title' => 'კვირის მიმოხილვა 26.10–01.11.2026',
                'excerpt' => 'გათიშვები: 3 (+50% წინა პერიოდთან). ყველაზე ხშირად — თბილისი.',
                'body' => "სულ გათიშვები: 3; წინა პერიოდთან შედარებით — +50%.\n\n- წყალი — 2\n- ელექტროენერგია — 1",
            ],
        ];
    }
}
