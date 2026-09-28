<?php

namespace Tests\Unit;

use App\Models\Address;
use App\Models\Event;
use App\Models\Subscriptions;
use Tests\TestCase;

class StreetFilterTest extends TestCase
{
    public function test_no_filter_matches_everything(): void
    {
        $this->assertTrue($this->subscription(null)->matches($this->event(['რუსთაველი 5'])));
        $this->assertTrue($this->subscription(' , ')->matches($this->event(['რუსთაველი 5'])));
    }

    public function test_matches_transliterated_address_case_insensitively(): void
    {
        $event = $this->event(['რუსთაველი 5', 'ნიკეას ქ. 12']);

        $this->assertTrue($this->subscription('Nikea')->matches($event));
        $this->assertFalse($this->subscription('chavchavadze')->matches($event));
    }

    public function test_matches_georgian_spelling(): void
    {
        $this->assertTrue($this->subscription('ნიკეა')->matches($this->event(['ნიკეას ქ. 12'])));
    }

    public function test_any_of_several_streets_matches(): void
    {
        $this->assertTrue($this->subscription('chavchavadze, nikea')->matches($this->event(['ნიკეას ქ. 12'])));
    }

    public function test_gas_event_without_addresses_matches_title(): void
    {
        $event = $this->event([], ['name_en' => 'Gas works on Nikea st']);

        $this->assertTrue($this->subscription('nikea')->matches($event));
        $this->assertFalse($this->subscription('rustaveli')->matches($event));
    }

    public function test_event_without_addresses_and_title_is_not_filtered_out(): void
    {
        $this->assertTrue($this->subscription('nikea')->matches($this->event([])));
    }

    public function test_parse_trims_lowercases_and_dedupes(): void
    {
        $this->assertSame(['nikea', 'rustaveli'], Subscriptions::parseStreetFilter(' Nikea ,rustaveli,, NIKEA'));
    }

    private function subscription(?string $filter): Subscriptions
    {
        return new Subscriptions(['street_filter' => $filter]);
    }

    private function event(array $addresses, array $attributes = []): Event
    {
        $event = new Event($attributes);
        $event->setRelation('addresses', collect($addresses)->map(fn ($name) => new Address(['name' => $name])));

        return $event;
    }
}
