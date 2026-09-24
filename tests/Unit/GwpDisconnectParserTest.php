<?php

namespace Tests\Unit;

use App\Support\GwpDisconnectParser;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class GwpDisconnectParserTest extends TestCase
{
    private Carbon $now;

    protected function setUp(): void
    {
        parent::setUp();
        $this->now = Carbon::create(2026, 9, 24, 12, 0, 0, 'Asia/Tbilisi');
    }

    public function test_full_period_in_month_day_year_format(): void
    {
        $text = 'ჩუღურეთი, თ. გამყრელიძის N2-თან   წყალმომარაგების ქსელზე დაზიანების გამო 9/24/2026 11:45 დან 9/24/2026 23:30 საათამდე წყალმომარაგება შეუწყდება: თ, გამყრელიძის ქუჩას და შესახვევს ( ყოფილი სუნდუკიანის), აღმაშენებლის 32/2, 39-41 / 1 ';

        [$start, $finish] = GwpDisconnectParser::parsePeriod($text, $this->now);

        $this->assertSame('2026-09-24 11:45', $start->format('Y-m-d H:i'));
        $this->assertSame('2026-09-24 23:30', $finish->format('Y-m-d H:i'));
        $this->assertSame(
            ['თ', 'გამყრელიძის ქუჩას და შესახვევს ( ყოფილი სუნდუკიანის)', 'აღმაშენებლის 32/2', '39-41 / 1'],
            GwpDisconnectParser::parseAddresses($text),
        );
    }

    public function test_emergency_with_restore_time_only(): void
    {
        $text = 'წყალმომარაგება შეუწყდათ ავარიულად მთაწმინდა, ამაღლების ქ. N31/33-ში  მოქალაქის კუთვნილ ქსელზე დაზიანების გამო 9/24/2026 23:00 საათამდე წყალმომარაგება შეუწყდა: ამაღლების N 31/33 ';

        [$start, $finish] = GwpDisconnectParser::parsePeriod($text, $this->now);

        $this->assertNull($start);
        $this->assertSame('2026-09-24 23:00', $finish->format('Y-m-d H:i'));
        $this->assertSame(['ამაღლების N 31/33'], GwpDisconnectParser::parseAddresses($text));
    }

    public function test_short_month_day_without_year(): void
    {
        $text = 'ვაკე, ჯანსუღ კორძაიას ქ. N1-თან ქსელზე აუცილებელი ტექნიკური სამუშაოები ტარდება წყალმომარაგება აღდგება 09/24 17:30 საათამდე . წყალმომარაგება შეწყვეტილი აქვს: ჯანსუღ კორძაიას ქ. N1 ';

        [$start, $finish] = GwpDisconnectParser::parsePeriod($text, $this->now);

        $this->assertNull($start);
        $this->assertSame('2026-09-24 17:30', $finish->format('Y-m-d H:i'));
        $this->assertSame(['ჯანსუღ კორძაიას ქ. N1'], GwpDisconnectParser::parseAddresses($text));
    }

    public function test_planned_day_month_with_district_groups(): void
    {
        $text = "გლდანი, არაგვის ხიდის მიმდებარედ მაგისტრალის გადაერთებითი სამუშაოების გამო, წყალი შეუწყდებათ 26/09 03:00 სთ-დან 27/09 15:00 სთ-მდე : ვაკის რაიონი:\nვაჟა-ფშაველას I, II კვარტლების, ნუცუბიძის ქ. N1-223 (კენტები).\n საბურთალოს რაიონი:\nვაშლიჯვრის დასახლების 2ა ზონები; სოფელი დიღომში: ვაჟა-ფშაველას";

        [$start, $finish] = GwpDisconnectParser::parsePeriod($text, $this->now);

        $this->assertSame('2026-09-26 03:00', $start->format('Y-m-d H:i'));
        $this->assertSame('2026-09-27 15:00', $finish->format('Y-m-d H:i'));
        $this->assertSame(
            ['ვაჟა-ფშაველას I', 'II კვარტლების', 'ნუცუბიძის ქ. N1-223 (კენტები)', 'ვაშლიჯვრის დასახლების 2ა ზონები', 'ვაჟა-ფშაველას'],
            GwpDisconnectParser::parseAddresses($text),
        );
    }

    public function test_short_date_across_new_year(): void
    {
        $now = Carbon::create(2026, 12, 31, 20, 0, 0, 'Asia/Tbilisi');

        [, $finish] = GwpDisconnectParser::parsePeriod('წყალმომარაგება აღდგება 01/01 10:00 საათამდე', $now);

        $this->assertSame('2027-01-01 10:00', $finish->format('Y-m-d H:i'));
    }
}
