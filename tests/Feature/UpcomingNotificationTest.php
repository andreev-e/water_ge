<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Notifications\EventNotification;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreatesOutageData;
use Tests\Concerns\PreparesDatabaseForTests;
use Tests\TestCase;

/**
 * Плановые отключения приходят подписчикам с фильтром по улицам за 10 минут
 * до начала (app:notify-upcoming), а не сразу после загрузки. Остальные
 * подписчики получают их, как раньше, вместе с загрузкой.
 */
class UpcomingNotificationTest extends TestCase
{
    use CreatesOutageData;
    use PreparesDatabaseForTests;

    private const NOW = '2026-11-04 12:00:00';

    private int $center;

    private int $address;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->travelTo(CarbonImmutable::parse(self::NOW));
        $this->prepareDatabaseForTests();

        $this->center = $this->serviceCenter();
        $this->address = $this->address($this->center, 'ნიკეას ქ. 12');
    }

    public function test_planned_outage_waits_for_street_filtered_subscribers(): void
    {
        $event = $this->outageModel('2026-11-04 12:30:00', planned: true);
        $this->subscribe(1);
        $this->subscribe(2, 'nikea');

        $this->assertSame(1, $event->notifySubscribed());

        $this->assertNotifiedOnDemand([1]);
        $this->assertFalse($event->fresh()->upcoming_notified);
    }

    public function test_reminder_is_sent_ten_minutes_before_the_start(): void
    {
        $event = $this->outageModel('2026-11-04 12:30:00', planned: true);
        $this->subscribe(1);
        $this->subscribe(2, 'nikea');
        $event->notifySubscribed();

        $this->travelTo(CarbonImmutable::parse('2026-11-04 12:19:00'));
        $this->artisan('app:notify-upcoming')->assertSuccessful();

        $this->assertNotifiedOnDemand([1]);

        $this->travelTo(CarbonImmutable::parse('2026-11-04 12:20:00'));
        $this->artisan('app:notify-upcoming')->assertSuccessful();

        $this->assertNotifiedOnDemand([1, 2]);
        $this->assertTrue($event->fresh()->upcoming_notified);

        // Повторный запуск не дублирует напоминание.
        $this->artisan('app:notify-upcoming')->assertSuccessful();
        $this->assertNotifiedOnDemand([1, 2]);
    }

    public function test_reminder_is_marked_as_upcoming(): void
    {
        $this->outageModel('2026-11-04 12:30:00', planned: true);
        $this->subscribe(2, 'nikea');

        $this->travelTo(CarbonImmutable::parse('2026-11-04 12:25:00'));
        $this->artisan('app:notify-upcoming')->assertSuccessful();

        $reminder = null;
        Notification::assertSentOnDemand(
            EventNotification::class,
            function (EventNotification $notification, array $channels, $notifiable) use (&$reminder) {
                if ((string) $notifiable->routes['telegram'] !== '2') {
                    return false;
                }

                $reminder = $notification;

                return true;
            },
        );

        $this->assertTrue($reminder->upcoming);
        $this->assertStringContainsString(
            __('telegram.upcoming', [], 'ru'),
            $reminder->toTelegram(null)->toArray()['text'],
        );
    }

    public function test_emergency_outage_is_sent_at_once(): void
    {
        $event = $this->outageModel('2026-11-04 12:30:00', planned: false);
        $this->subscribe(1);
        $this->subscribe(2, 'nikea');

        $this->assertSame(2, $event->notifySubscribed());
        $this->assertNotifiedOnDemand([1, 2]);
    }

    public function test_planned_outage_within_the_window_is_sent_at_once_and_not_repeated(): void
    {
        $event = $this->outageModel('2026-11-04 12:05:00', planned: true);
        $this->subscribe(1);
        $this->subscribe(2, 'nikea');

        $this->assertSame(2, $event->notifySubscribed());
        $this->assertTrue($event->fresh()->upcoming_notified);

        $this->artisan('app:notify-upcoming')->assertSuccessful();
        $this->assertNotifiedOnDemand([1, 2]);
    }

    public function test_requested_events_ignore_the_reminder_window(): void
    {
        $event = $this->outageModel('2026-11-04 12:30:00', planned: true);
        $this->subscribe(2, 'nikea');

        // Так бот присылает события в ответ на сообщение: подписчик попросил сам.
        $this->assertSame(1, $event->notifySubscribed(2, false));
        $this->assertNotifiedOnDemand([2]);
        $this->assertFalse($event->fresh()->upcoming_notified);
    }

    private function outageModel(string $start, bool $planned): Event
    {
        $id = $this->outage($this->center, $start, '2026-11-04 15:00:00', 'water', $planned, 100, [$this->address]);

        return Event::query()->findOrFail($id);
    }

    private function subscribe(int $botUserId, ?string $streetFilter = null): void
    {
        DB::table('bot_user')->updateOrInsert(
            ['id' => $botUserId],
            ['is_bot' => false, 'created_at' => now(), 'updated_at' => now()],
        );

        DB::table('subscriptions')->insert([
            'bot_user_id' => $botUserId,
            'service_center_id' => $this->center,
            'street_filter' => $streetFilter,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  int[]  $botUserIds
     */
    private function assertNotifiedOnDemand(array $botUserIds): void
    {
        Notification::assertSentOnDemandTimes(EventNotification::class, count($botUserIds));

        foreach ($botUserIds as $botUserId) {
            Notification::assertSentOnDemand(
                EventNotification::class,
                fn(EventNotification $notification, array $channels, $notifiable) => (string) $notifiable->routes['telegram'] === (string) $botUserId,
            );
        }
    }
}
