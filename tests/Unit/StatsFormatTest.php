<?php

namespace Tests\Unit;

use App\Support\StatsFormat;
use Tests\TestCase;

class StatsFormatTest extends TestCase
{
    public function test_thousands_are_split_per_language(): void
    {
        $this->assertSame('1 234 567', StatsFormat::number(1234567, 'ru'));
        $this->assertSame('1 234 567', StatsFormat::number(1234567, 'ka'));
        $this->assertSame('1,234,567', StatsFormat::number(1234567, 'en'));
    }

    public function test_decimal_separator_follows_the_language(): void
    {
        $this->assertSame('2,5', StatsFormat::number(2.5, 'ru', 1));
        $this->assertSame('2.5', StatsFormat::number(2.5, 'en', 1));
    }

    public function test_missing_number_is_a_dash(): void
    {
        $this->assertSame('—', StatsFormat::number(null, 'ru'));
        $this->assertSame('—', StatsFormat::percent(null, 'ru'));
        $this->assertSame('—', StatsFormat::change(null, 'ru'));
        $this->assertSame('—', StatsFormat::hours(null, 'ru'));
    }

    public function test_share_becomes_a_percent(): void
    {
        $this->assertSame('50%', StatsFormat::percent(0.5, 'ru'));
        $this->assertSame('12,3%', StatsFormat::percent(0.1234, 'ru'));
        $this->assertSame('12.3%', StatsFormat::percent(0.1234, 'en'));
    }

    public function test_change_keeps_the_sign(): void
    {
        $this->assertSame('+12,3%', StatsFormat::change(12.34, 'ru'));
        $this->assertSame('+12.3%', StatsFormat::change(12.34, 'en'));
        $this->assertSame('-8,5%', StatsFormat::change(-8.5, 'ru'));
        $this->assertSame('0%', StatsFormat::change(0.0, 'ru'));
        $this->assertSame('0%', StatsFormat::change(-0.04, 'ru'));
    }

    public function test_duration_is_hours_in_the_language_unit(): void
    {
        $this->assertSame('14,5 ч', StatsFormat::hours(14.5, 'ru'));
        $this->assertSame('14.5 h', StatsFormat::hours(14.5, 'en'));
        $this->assertSame('14,5 სთ', StatsFormat::hours(14.5, 'ka'));
    }
}
