<?php

namespace Tests\Unit;

use App\Services\Publications\DigestWriter;
use Tests\Concerns\BuildsDigestStatistics;
use Tests\TestCase;

class DigestWriterTest extends TestCase
{
    use BuildsDigestStatistics;

    public function test_text_is_written_in_every_language_of_the_site(): void
    {
        $texts = (new DigestWriter())->write($this->digestStatistics());

        $this->assertSame(['ru', 'en', 'ka'], array_keys($texts));
    }

    public function test_title_and_excerpt_carry_the_numbers_of_the_period(): void
    {
        $texts = (new DigestWriter())->write($this->digestStatistics());

        $this->assertSame('26.10–01.11.2026: отключений — 3', $texts['ru']['title']);
        $this->assertSame('Отключений: 3 (+50% к прошлому периоду). Чаще всего — Тбилиси.', $texts['ru']['excerpt']);
        $this->assertSame('26.10–01.11.2026: 3 outages', $texts['en']['title']);
        $this->assertSame('26.10–01.11.2026: გათიშვები — 3', $texts['ka']['title']);
    }

    public function test_body_tells_what_happened_in_plain_words(): void
    {
        $body = (new DigestWriter())->write($this->digestStatistics())['ru']['body'];

        $this->assertStringContainsString('Всего отключений: 3; к прошлому периоду — +50%.', $body);
        $this->assertStringContainsString('Вода — 2, электричество — 1, газ — 0. Плановых: 2, аварийных: 1.', $body);
        $this->assertStringContainsString('Чаще всего отключали: Тбилиси — 3.', $body);
        $this->assertStringContainsString('Самый тяжёлый день: 27 октября, отключений — 2.', $body);
        $this->assertStringContainsString('Затронуто адресов: 2, потребителей: 340.', $body);
        $this->assertStringContainsString('Обычное отключение длилось 2,5 ч (медиана)', $body);
        $this->assertStringContainsString('Пользователей в боте: 100, подписок: 60, новых подписок за период: 4.', $body);
    }

    public function test_centers_are_named_in_the_language_of_the_page(): void
    {
        $texts = (new DigestWriter())->write($this->digestStatistics());

        $this->assertStringContainsString('Тбилиси', $texts['ru']['body']);
        $this->assertStringContainsString('Tbilisi', $texts['en']['body']);
        $this->assertStringContainsString('თბილისი', $texts['ka']['body']);
    }

    public function test_too_few_outages_are_called_preliminary(): void
    {
        $texts = (new DigestWriter())->write($this->digestStatistics(['reliable' => false]));

        $this->assertStringEndsWith('Отключений слишком мало для выводов — цифры предварительные.', $texts['ru']['body']);
        $this->assertStringEndsWith(
            'Too few outages to draw conclusions — the numbers are preliminary.',
            $texts['en']['body'],
        );
    }

    public function test_period_without_outages_has_no_numbers(): void
    {
        $texts = (new DigestWriter())->write($this->digestStatistics([
            'reliable' => false,
            'outages' => [
                'total' => 0,
                'top_centers' => [],
                'change_percent' => null,
                'median_duration_hours' => null,
                'longest' => null,
                'busiest_day' => null,
            ],
            'audience' => ['users_end' => null, 'subscriptions_end' => null, 'new_subscriptions' => 0],
        ]));

        $this->assertSame('26.10–01.11.2026: отключений не было', $texts['ru']['title']);
        $this->assertSame('За 26.10–01.11.2026 отключений не было.', $texts['ru']['excerpt']);
        $this->assertSame('За 26.10–01.11.2026 отключений в базе нет.', $texts['ru']['body']);
    }

    public function test_first_period_without_a_previous_one_has_no_change(): void
    {
        $texts = (new DigestWriter())->write($this->digestStatistics([
            'outages' => ['change_percent' => null],
        ]));

        $this->assertSame('Отключений: 3. Чаще всего — Тбилиси.', $texts['ru']['excerpt']);
        $this->assertStringContainsString('Всего отключений: 3.', $texts['ru']['body']);
    }
}
