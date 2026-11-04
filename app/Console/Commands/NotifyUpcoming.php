<?php

namespace App\Console\Commands;

use App\Models\Event;
use Illuminate\Console\Command;

/**
 * At load time Event::notifySubscribed() skips street-filtered subscribers for
 * planned outages, so that they hear about it right before it starts, not days
 * in advance. This command sends that reminder once per event.
 */
class NotifyUpcoming extends Command
{
    protected $signature = 'app:notify-upcoming';

    protected $description = 'Reminds street-filtered subscribers about planned outages starting soon';

    public function handle(): int
    {
        $events = Event::query()
            ->where('planned', true)
            ->where('upcoming_notified', false)
            ->where('start', '>', now())
            ->where('start', '<=', now()->addMinutes(Event::UPCOMING_MINUTES))
            ->get();

        foreach ($events as $event) {
            // Marked first: a failure while queueing must not repeat the message every minute.
            $event->forceFill(['upcoming_notified' => true])->save();

            $count = $event->notifyUpcomingStreetFiltered();
            $this->info('Event ' . $event->id . ': notified ' . $count . ' before the start');
        }

        return self::SUCCESS;
    }
}
