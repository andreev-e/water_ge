<?php

namespace Tests\Feature;

use App\Models\Publication;
use Tests\Concerns\CreatesPublications;
use Tests\Concerns\PreparesDatabaseForTests;
use Tests\TestCase;

class PublicationsPageTest extends TestCase
{
    use CreatesPublications;
    use PreparesDatabaseForTests;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareDatabaseForTests();
    }

    public function test_list_shows_published_digests_in_the_language_of_the_page(): void
    {
        $this->publication();

        $this->get('/publications')
            ->assertOk()
            ->assertSee('Сводки отключений')
            ->assertSee('Сводка за неделю 26.10–01.11.2026')
            ->assertSee('Читать сводку')
            ->assertDontSee('Weekly digest');

        $this->get('/en/publications')
            ->assertOk()
            ->assertSee('Outage digests')
            ->assertSee('Weekly digest 26.10–01.11.2026')
            ->assertDontSee('Сводка за неделю');

        $this->get('/ka/publications')
            ->assertOk()
            ->assertSee('კვირის მიმოხილვა 26.10–01.11.2026');
    }

    public function test_list_explains_when_there_is_nothing_to_read(): void
    {
        $this->get('/publications')->assertOk()->assertSee('Сводок пока нет');
        $this->get('/en/publications')->assertOk()->assertSee('No digests yet');
    }

    public function test_draft_is_invisible_to_readers(): void
    {
        $this->publication(['status' => Publication::STATUS_DRAFT, 'published_at' => null]);

        $this->get('/publications')->assertOk()->assertSee('Сводок пока нет');
        $this->get('/publications/week-2026-10-26')->assertNotFound();
    }

    public function test_digest_page_shows_the_text_and_the_key_numbers(): void
    {
        $this->publication();

        $response = $this->get('/publications/week-2026-10-26');

        $response->assertOk()
            ->assertSee('Сводка за неделю 26.10–01.11.2026')
            ->assertSee('Отключений: 3 (+50% к прошлому периоду). Чаще всего — Тбилиси.')
            ->assertSee('Всего отключений: 3; к прошлому периоду — +50%.')
            ->assertSee('Вода — 2')
            ->assertSee('Электричество — 1')
            ->assertSee('Главное в цифрах')
            ->assertSee('+50%')
            ->assertSee('340')
            ->assertSee('Чаще всего отключали')
            ->assertSee('Тбилиси')
            ->assertSee('/service-centers/1', false)
            ->assertSee('Все сводки')
            ->assertSee('Опубликовано 2 ноября 2026');
    }

    public function test_missing_language_falls_back_to_russian_text(): void
    {
        $texts = $this->digestTexts();
        $this->publication([], ['ru' => $texts['ru']]);

        $this->get('/ka/publications/week-2026-10-26')
            ->assertOk()
            ->assertSee('Сводка за неделю 26.10–01.11.2026');

        $this->get('/ka/publications')
            ->assertOk()
            ->assertSee('Сводка за неделю 26.10–01.11.2026');
    }

    public function test_digest_without_russian_text_is_not_shown_at_all(): void
    {
        $texts = $this->digestTexts();
        $this->publication([], ['en' => $texts['en']]);

        $this->get('/publications')->assertOk()->assertSee('Сводок пока нет');
        $this->get('/publications/week-2026-10-26')->assertNotFound();
    }

    public function test_unknown_digest_is_not_found(): void
    {
        $this->get('/publications/week-2000-01-03')->assertNotFound();
    }
}
