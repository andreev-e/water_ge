<?php

namespace Tests\Unit;

use App\Models\Publication;
use App\Models\PublicationTranslation;
use Tests\TestCase;

class PublicationTest extends TestCase
{
    public function test_translation_comes_in_the_language_of_the_page(): void
    {
        $publication = $this->publication(['ru', 'en']);

        $this->assertSame('en', $publication->translation('en')?->locale);
        $this->assertSame('ru', $publication->translation('ru')?->locale);
    }

    public function test_missing_translation_falls_back_to_russian(): void
    {
        $publication = $this->publication(['ru']);

        $this->assertSame('ru', $publication->translation('ka')?->locale);
    }

    public function test_without_russian_text_there_is_nothing_to_show(): void
    {
        $publication = $this->publication(['en']);

        $this->assertNull($publication->translation('ka'));
    }

    public function test_only_a_published_publication_with_a_date_is_visible(): void
    {
        $this->assertTrue($this->draft()->isPublished());
        $this->assertFalse($this->draft(['status' => Publication::STATUS_DRAFT])->isPublished());
        $this->assertFalse($this->draft(['published_at' => null])->isPublished());
    }

    public function test_period_label_is_written_in_the_language(): void
    {
        $publication = $this->draft(['type' => Publication::TYPE_MONTH, 'period_start' => '2026-10-01', 'period_end' => '2026-10-31']);

        $this->assertSame('октябрь 2026', $publication->periodLabel('ru'));
        $this->assertSame('October 2026', $publication->periodLabel('en'));
    }

    public function test_week_period_label_is_dates(): void
    {
        $this->assertSame('26.10–01.11.2026', $this->draft()->periodLabel('ru'));
    }

    public function test_url_carries_the_language_and_the_shared_slug(): void
    {
        $publication = $this->draft();

        $this->assertSame(url('/publications/week-2026-10-26'), $publication->url('ru'));
        $this->assertSame(url('/en/publications/week-2026-10-26'), $publication->url('en'));
        $this->assertSame(url('/ka/publications/week-2026-10-26'), $publication->url('ka'));
    }

    /**
     * Публикация в памяти: тесту не нужна база, только строки перевода.
     *
     * @param  string[]  $locales
     */
    private function publication(array $locales): Publication
    {
        $publication = $this->draft();
        $publication->setRelation('translations', collect($locales)->map(
            fn (string $locale) => new PublicationTranslation(['locale' => $locale, 'title' => $locale]),
        ));

        return $publication;
    }

    /** @param array<string, mixed> $attributes */
    private function draft(array $attributes = []): Publication
    {
        return new Publication($attributes + [
            'slug' => 'week-2026-10-26',
            'type' => Publication::TYPE_WEEK,
            'period_start' => '2026-10-26',
            'period_end' => '2026-11-01',
            'status' => Publication::STATUS_PUBLISHED,
            'published_at' => '2026-11-02 09:00:00',
        ]);
    }
}
