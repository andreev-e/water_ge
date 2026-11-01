<?php

namespace Tests\Feature;

use App\Models\Publication;
use Carbon\CarbonImmutable;
use Tests\Concerns\CreatesOutageData;
use Tests\Concerns\PreparesDatabaseForTests;
use Tests\TestCase;

/**
 * Команда publications:digest собирает сводку из базы: три отключения на неделе
 * 26.10–01.11.2026 и два на прошлой — сводка должна показать 3 и +50%.
 */
class GeneratePublicationCommandTest extends TestCase
{
    use CreatesOutageData;
    use PreparesDatabaseForTests;

    private int $center;

    private int $address;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareDatabaseForTests();

        $this->center = $this->serviceCenter();
        $this->address = $this->address($this->center, 'რუსთაველი 5');
    }

    public function test_week_digest_is_built_from_the_data(): void
    {
        $this->weekData();

        $this->artisan('publications:digest', ['--type' => 'week', '--period' => '2026-W44'])
            ->expectsOutputToContain('Сводка week-2026-10-26: 3 отключений, опубликована, создана.')
            ->assertSuccessful();

        $publication = $this->publication();

        $this->assertSame(Publication::TYPE_WEEK, $publication->type);
        $this->assertSame(Publication::STATUS_PUBLISHED, $publication->status);
        $this->assertNotNull($publication->published_at);
        $this->assertSame('2026-10-26', $publication->period_start->toDateString());
        $this->assertSame('2026-11-01', $publication->period_end->toDateString());

        $outages = $publication->statistics['outages'];
        $this->assertTrue($publication->statistics['reliable']);
        $this->assertSame(3, $outages['total']);
        $this->assertSame(2, $outages['by_type']['water']);
        $this->assertSame(1, $outages['by_type']['energy']);
        $this->assertSame(0, $outages['by_type']['gas']);
        $this->assertSame(2, $outages['planned']);
        $this->assertSame(1, $outages['emergency']);
        $this->assertEqualsWithDelta(50.0, $outages['change_percent'], 0.01);
        $this->assertEqualsWithDelta(2.0, $outages['median_duration_hours'], 0.01);
        $this->assertEqualsWithDelta(3.0, $outages['longest']['hours'], 0.01);
        $this->assertSame('water', $outages['longest']['type']);
        $this->assertSame('Тбилиси', $outages['longest']['center']['ru']);
        $this->assertSame('2026-10-27', $outages['busiest_day']['date']);
        $this->assertSame(1, $outages['addresses']);
        $this->assertSame(340, $outages['customers']);
        $this->assertSame('Тбилиси', $outages['top_centers'][0]['names']['ru']);
        $this->assertSame(3, $outages['top_centers'][0]['events']);
        $this->assertSame('რუსთაველი 5', $outages['top_addresses'][0]['names']['ka']);
        $this->assertSame(2, $outages['top_addresses'][0]['events']);

        $audience = $publication->statistics['audience'];
        $this->assertSame(90, $audience['users_start']);
        $this->assertSame(100, $audience['users_end']);
        $this->assertSame(1, $audience['new_subscriptions']);
    }

    public function test_digest_speaks_three_languages_and_appears_on_the_pages(): void
    {
        $this->weekData();

        $this->artisan('publications:digest', ['--type' => 'week', '--period' => '2026-W44'])->assertSuccessful();

        $publication = $this->publication()->load('translations');

        $this->assertSame(['en', 'ka', 'ru'], $publication->translations->pluck('locale')->sort()->values()->all());
        $this->assertSame('26.10–01.11.2026: отключений — 3', $publication->translation('ru')->title);
        $this->assertSame('26.10–01.11.2026: 3 outages', $publication->translation('en')->title);
        $this->assertSame('26.10–01.11.2026: გათიშვები — 3', $publication->translation('ka')->title);

        $body = $publication->translation('ru')->body;
        $this->assertStringContainsString('Всего отключений: 3; к прошлому периоду — +50%.', $body);
        $this->assertStringContainsString('Вода — 2, электричество — 1, газ — 0. Плановых: 2, аварийных: 1.', $body);
        $this->assertStringContainsString('Самый тяжёлый день: 27 октября, отключений — 2.', $body);
        $this->assertStringContainsString('Затронуто адресов: 1, потребителей: 340.', $body);
        $this->assertStringContainsString('Пользователей в боте: 100, подписок: 60, новых подписок за период: 1.', $body);
        $this->assertStringNotContainsString('предварительные', $body);

        $this->get('/publications')
            ->assertOk()
            ->assertSee('26.10–01.11.2026: отключений — 3');

        $this->get('/publications/week-2026-10-26')
            ->assertOk()
            ->assertSee('Всего отключений: 3; к прошлому периоду — +50%.')
            ->assertSee('Тбилиси');
    }

    public function test_existing_digest_is_kept_without_force(): void
    {
        $this->weekData();
        $this->artisan('publications:digest', ['--type' => 'week', '--period' => '2026-W44'])->assertSuccessful();
        $publishedAt = $this->publication()->published_at;

        $this->outage($this->center, '2026-10-30 09:00:00', '2026-10-30 10:00:00', 'gas', true, 10, [$this->address]);

        $this->artisan('publications:digest', ['--type' => 'week', '--period' => '2026-W44'])
            ->expectsOutputToContain('уже есть')
            ->assertSuccessful();

        $publication = $this->publication();
        $this->assertSame(3, $publication->statistics['outages']['total']);
        $this->assertEquals($publishedAt, $publication->published_at);
        $this->assertSame(1, Publication::query()->count());
    }

    public function test_force_rebuilds_the_digest_without_duplicating_the_texts(): void
    {
        $this->weekData();
        $this->artisan('publications:digest', ['--type' => 'week', '--period' => '2026-W44'])->assertSuccessful();

        $this->outage($this->center, '2026-10-30 09:00:00', '2026-10-30 10:00:00', 'gas', true, 10, [$this->address]);

        $this->artisan('publications:digest', ['--type' => 'week', '--period' => '2026-W44', '--force' => true])
            ->expectsOutputToContain('перезаписана')
            ->assertSuccessful();

        $publication = $this->publication();
        $this->assertSame(4, $publication->statistics['outages']['total']);
        $this->assertSame(3, $publication->translations()->count());
        $this->assertSame(1, Publication::query()->count());
    }

    public function test_draft_is_saved_but_not_published(): void
    {
        $this->weekData();

        $this->artisan('publications:digest', ['--type' => 'week', '--period' => '2026-W44', '--draft' => true])
            ->expectsOutputToContain('черновик')
            ->assertSuccessful();

        $publication = $this->publication();
        $this->assertSame(Publication::STATUS_DRAFT, $publication->status);
        $this->assertNull($publication->published_at);
        $this->assertSame(3, $publication->translations()->count());

        $this->get('/publications')->assertOk()->assertDontSee('26.10–01.11.2026: отключений — 3');
        $this->get('/publications/week-2026-10-26')->assertNotFound();
    }

    public function test_dry_run_shows_the_numbers_and_saves_nothing(): void
    {
        $this->weekData();

        $this->artisan('publications:digest', ['--type' => 'week', '--period' => '2026-W44', '--dry-run' => true])
            ->expectsOutputToContain('[ru] 26.10–01.11.2026: отключений — 3')
            ->expectsOutputToContain('[ka] 26.10–01.11.2026: გათიშვები — 3')
            ->assertSuccessful();

        $this->assertSame(0, Publication::query()->count());
    }

    public function test_period_and_type_are_checked(): void
    {
        $this->artisan('publications:digest', ['--type' => 'year'])
            ->expectsOutputToContain('Тип сводки: week или month.')
            ->assertExitCode(1);

        $this->artisan('publications:digest', ['--type' => 'week', '--period' => 'на прошлой неделе'])
            ->expectsOutputToContain('Период не разобран')
            ->assertExitCode(1);

        $this->artisan('publications:digest', ['--type' => 'month', '--period' => '2026-10-26'])
            ->expectsOutputToContain('Период не разобран')
            ->assertExitCode(1);

        $this->assertSame(0, Publication::query()->count());
    }

    public function test_month_digest_covers_the_whole_month(): void
    {
        $this->outage($this->center, '2026-10-05 09:00:00', '2026-10-05 11:00:00', 'water', true, 100, [$this->address]);
        $this->outage($this->center, '2026-10-27 10:00:00', '2026-10-27 12:00:00', 'water', true, 40, [$this->address]);
        $this->outage($this->center, '2026-10-29 08:00:00', '2026-10-29 10:00:00', 'energy', false, 200);
        $this->outage($this->center, '2026-09-30 08:00:00', '2026-09-30 10:00:00', 'gas');

        $this->artisan('publications:digest', ['--type' => 'month', '--period' => '2026-10'])->assertSuccessful();

        $publication = $this->publication();
        $this->assertSame('month-2026-10', $publication->slug);
        $this->assertSame('2026-10-01', $publication->period_start->toDateString());
        $this->assertSame('2026-10-31', $publication->period_end->toDateString());
        $this->assertSame(3, $publication->statistics['outages']['total']);
        $this->assertSame('октябрь 2026: отключений — 3', $publication->translation('ru')->title);
        $this->assertSame('October 2026: 3 outages', $publication->translation('en')->title);
    }

    public function test_without_a_period_the_digest_is_built_for_the_previous_week(): void
    {
        $this->weekData();
        $this->travelTo(CarbonImmutable::parse('2026-11-02 06:00:00'));

        $this->artisan('publications:digest', ['--type' => 'week'])->assertSuccessful();

        $publication = $this->publication();
        $this->assertSame('week-2026-10-26', $publication->slug);
        $this->assertSame(3, $publication->statistics['outages']['total']);
    }

    public function test_few_outages_make_the_digest_preliminary(): void
    {
        $this->outage($this->center, '2026-10-27 10:00:00', '2026-10-27 12:00:00', 'water', true, 100, [$this->address]);

        $this->artisan('publications:digest', ['--type' => 'week', '--period' => '2026-W44'])->assertSuccessful();

        $publication = $this->publication();
        $this->assertFalse($publication->statistics['reliable']);
        $this->assertStringEndsWith(
            'Отключений слишком мало для выводов — цифры предварительные.',
            $publication->translation('ru')->body,
        );
    }

    public function test_period_without_outages_says_so_instead_of_inventing_numbers(): void
    {
        $this->artisan('publications:digest', ['--type' => 'week', '--period' => '2026-W44'])->assertSuccessful();

        $publication = $this->publication();
        $this->assertSame(0, $publication->statistics['outages']['total']);
        $this->assertSame('26.10–01.11.2026: отключений не было', $publication->translation('ru')->title);
        $this->assertSame('За 26.10–01.11.2026 отключений в базе нет.', $publication->translation('ru')->body);
    }

    /** События и снимки статистики вокруг недели 26.10–01.11.2026. */
    private function weekData(): void
    {
        $this->outage($this->center, '2026-10-27 10:00:00', '2026-10-27 12:00:00', 'water', true, 100, [$this->address]);
        $this->outage($this->center, '2026-10-27 18:00:00', '2026-10-27 21:00:00', 'water', true, 40, [$this->address]);
        $this->outage($this->center, '2026-10-29 08:00:00', '2026-10-29 10:00:00', 'energy', false, 200);

        // Прошлая неделя — для сравнения: два отключения, значит +50%.
        $this->outage($this->center, '2026-10-21 10:00:00', '2026-10-21 11:00:00', 'gas', true, 50);
        $this->outage($this->center, '2026-10-22 09:00:00', '2026-10-22 10:00:00', 'water', true, 30);

        // Далеко за пределами обоих периодов.
        $this->outage($this->center, '2026-09-15 10:00:00', '2026-09-15 11:00:00', 'gas', true, 70);

        $this->userStat('2026-10-25', 90, 50);
        $this->userStat('2026-11-01', 100, 60);

        // Подписка внутри периода и одна старая.
        $this->subscription('2026-10-28 12:00:00', 1, $this->center);
        $this->subscription('2026-10-20 12:00:00', 2, $this->center);
    }

    private function publication(): Publication
    {
        return Publication::query()->firstOrFail();
    }
}
