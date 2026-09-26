<?php

namespace Tests\Unit;

use App\Enums\EventTypes;
use App\Models\Address;
use App\Models\Event;
use App\Models\ServiceCenter;
use App\Support\FacebookPost;
use Carbon\Carbon;
use Tests\TestCase;

class FacebookPostTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::create(2026, 9, 24, 12, 0, 0, 'Asia/Tbilisi'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_water_post_lists_addresses_and_hashtags(): void
    {
        $event = $this->makeEvent(EventTypes::water, ['name' => 'ბათუმი', 'name_ru' => 'Батуми'], ['ჭავჭავაძე 1', 'რუსთაველი 5']);

        $this->assertSame(
            "🚫💧Батуми: Сегодня 14:00 - 18:00\n\nchavchavadze 1\nrustaveli 5\n\n#Батуми #вода #отключения",
            FacebookPost::text($event),
        );
    }

    public function test_long_address_list_is_truncated(): void
    {
        $addresses = array_map(fn ($i) => 'ქუჩა ' . $i, range(1, FacebookPost::SHOW_IN_POST + 3));
        $event = $this->makeEvent(EventTypes::energy, ['name' => 'ქუთაისი', 'name_ru' => 'Кутаиси'], $addresses);

        $text = FacebookPost::text($event);

        $this->assertStringContainsString('... всего адресов: ' . count($addresses), $text);
        $this->assertStringNotContainsString('qucha ' . (FacebookPost::SHOW_IN_POST + 1) . "\n", $text);
        $this->assertStringEndsWith('#Кутаиси #свет #отключения', $text);
    }

    public function test_gas_post_uses_event_name_and_falls_back_to_georgian_center_name(): void
    {
        $event = $this->makeEvent(EventTypes::gas, ['name' => 'ზესტაფონი'], [], ['name_en' => 'Gas works on Main st']);

        $this->assertSame(
            "🚫🔥ზესტაფონი: Сегодня 14:00 - 18:00\nGas works on Main st\n\n#ზესტაფონი #газ #отключения",
            FacebookPost::text($event),
        );
    }

    public function test_hashtag_strips_spaces_and_punctuation(): void
    {
        $this->assertSame('#НижняяСванети', FacebookPost::hashtag('Нижняя Сванети'));
        $this->assertSame('#Тбилисицентр', FacebookPost::hashtag('Тбилиси (центр)'));
        $this->assertNull(FacebookPost::hashtag(' - '));
    }

    private function makeEvent(EventTypes $type, array $serviceCenter, array $addresses, array $attributes = []): Event
    {
        $event = new Event(array_merge([
            'type' => $type,
            'start' => Carbon::create(2026, 9, 24, 14, 0, 0, 'Asia/Tbilisi'),
            'finish' => Carbon::create(2026, 9, 24, 18, 0, 0, 'Asia/Tbilisi'),
        ], $attributes));

        $event->setRelation('serviceCenter', new ServiceCenter($serviceCenter));
        $event->setRelation('addresses', collect($addresses)->map(fn ($name) => new Address(['name' => $name])));

        return $event;
    }
}
