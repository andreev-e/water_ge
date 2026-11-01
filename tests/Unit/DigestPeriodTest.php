<?php

namespace Tests\Unit;

use App\Models\Publication;
use App\Services\Publications\DigestPeriod;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DigestPeriodTest extends TestCase
{
    public function test_week_starts_on_monday(): void
    {
        $start = DigestPeriod::start(Publication::TYPE_WEEK, CarbonImmutable::parse('2026-10-29 15:30'));

        $this->assertSame('2026-10-26', $start->toDateString());
    }

    public function test_week_ends_on_sunday(): void
    {
        $end = DigestPeriod::end(Publication::TYPE_WEEK, CarbonImmutable::parse('2026-10-26'));

        $this->assertSame('2026-11-01', $end->toDateString());
    }

    public function test_month_starts_on_the_first_and_covers_the_whole_month(): void
    {
        $start = DigestPeriod::start(Publication::TYPE_MONTH, CarbonImmutable::parse('2026-10-29'));

        $this->assertSame('2026-10-01', $start->toDateString());
        $this->assertSame('2026-10-31', DigestPeriod::end(Publication::TYPE_MONTH, $start)->toDateString());
    }

    public function test_february_of_a_leap_year_ends_on_the_29th(): void
    {
        $start = DigestPeriod::start(Publication::TYPE_MONTH, CarbonImmutable::parse('2028-02-10'));

        $this->assertSame('2028-02-29', DigestPeriod::end(Publication::TYPE_MONTH, $start)->toDateString());
    }

    public function test_previous_week_is_the_week_before(): void
    {
        $previous = DigestPeriod::previousStart(Publication::TYPE_WEEK, CarbonImmutable::parse('2026-10-26'));

        $this->assertSame('2026-10-19', $previous->toDateString());
    }

    public function test_previous_month_does_not_overflow_from_a_long_month(): void
    {
        $this->assertSame(
            '2026-02-01',
            DigestPeriod::previousStart(Publication::TYPE_MONTH, CarbonImmutable::parse('2026-03-01'))->toDateString(),
        );
        $this->assertSame(
            '2025-12-01',
            DigestPeriod::previousStart(Publication::TYPE_MONTH, CarbonImmutable::parse('2026-01-01'))->toDateString(),
        );
    }

    public function test_slug_is_the_same_for_every_language(): void
    {
        // Команда передаёт сюда начало периода, поэтому слаг не зависит от дня запуска.
        $this->assertSame('week-2026-10-26', DigestPeriod::slug(Publication::TYPE_WEEK, CarbonImmutable::parse('2026-10-26')));
        $this->assertSame('month-2026-10', DigestPeriod::slug(Publication::TYPE_MONTH, CarbonImmutable::parse('2026-10-01')));
    }

    public function test_period_data_carries_the_machine_label(): void
    {
        $data = DigestPeriod::data(
            Publication::TYPE_WEEK,
            CarbonImmutable::parse('2026-10-26'),
            CarbonImmutable::parse('2026-11-01'),
        );

        $this->assertSame(['type' => 'week', 'start' => '2026-10-26', 'end' => '2026-11-01', 'label' => '2026-W44'], $data);

        $month = DigestPeriod::data(
            Publication::TYPE_MONTH,
            CarbonImmutable::parse('2026-10-01'),
            CarbonImmutable::parse('2026-10-31'),
        );

        $this->assertSame('2026-10', $month['label']);
    }

    public function test_period_from_iso_week(): void
    {
        $start = DigestPeriod::fromString(Publication::TYPE_WEEK, '2026-W44');

        $this->assertSame('2026-10-26', $start?->toDateString());
    }

    public function test_period_from_any_day_of_the_week(): void
    {
        $start = DigestPeriod::fromString(Publication::TYPE_WEEK, '2026-11-01');

        $this->assertSame('2026-10-26', $start?->toDateString());
    }

    public function test_period_from_month(): void
    {
        $start = DigestPeriod::fromString(Publication::TYPE_MONTH, '2026-10');

        $this->assertSame('2026-10-01', $start?->toDateString());
    }

    #[DataProvider('invalidPeriods')]
    public function test_invalid_period_is_rejected(string $type, string $value): void
    {
        $this->assertNull(DigestPeriod::fromString($type, $value));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function invalidPeriods(): array
    {
        return [
            'неделя без номера' => [Publication::TYPE_WEEK, 'week-44'],
            'неделя словами' => [Publication::TYPE_WEEK, 'на прошлой неделе'],
            'месяц без нуля' => [Publication::TYPE_MONTH, '2026-1'],
            'месяц с днём' => [Publication::TYPE_MONTH, '2026-10-26'],
            'несуществующая дата' => [Publication::TYPE_WEEK, '2026-13-45'],
        ];
    }

    public function test_empty_period_is_the_previous_week(): void
    {
        $start = DigestPeriod::fromString(Publication::TYPE_WEEK, null);

        $this->assertNotNull($start);
        $this->assertTrue($start->isMonday());
        $this->assertTrue($start->lessThan(CarbonImmutable::today()->startOfWeek()->startOfDay()));
        $this->assertSame(
            DigestPeriod::start(Publication::TYPE_WEEK, CarbonImmutable::today()->subWeek())->toDateString(),
            $start->toDateString(),
        );
    }

    public function test_empty_period_is_the_previous_month(): void
    {
        $start = DigestPeriod::fromString(Publication::TYPE_MONTH, ' ');

        $this->assertNotNull($start);
        $this->assertSame('01', $start->format('d'));
        $this->assertTrue($start->lessThan(CarbonImmutable::today()->startOfMonth()));
    }

    public function test_week_label_is_the_same_in_every_language(): void
    {
        $period = ['type' => 'week', 'start' => '2026-10-26', 'end' => '2026-11-01'];

        foreach (['ru', 'en', 'ka'] as $locale) {
            $this->assertSame('26.10–01.11.2026', DigestPeriod::label($period, $locale));
        }
    }

    public function test_month_label_is_translated(): void
    {
        $period = ['type' => 'month', 'start' => '2026-10-01', 'end' => '2026-10-31'];

        $this->assertSame('октябрь 2026', DigestPeriod::label($period, 'ru'));
        $this->assertSame('October 2026', DigestPeriod::label($period, 'en'));
        $this->assertSame('ოქტომბერს 2026', DigestPeriod::label($period, 'ka'));
    }
}
