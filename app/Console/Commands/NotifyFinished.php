<?php

namespace App\Console\Commands;

use App\Models\Event;
use Illuminate\Console\Command;

class NotifyFinished extends Command
{
    /**
     * An outage loaded long after it ended (archive import, a source that was
     * down) is history, not news.
     */
    const MAX_DELAY_HOURS = 3;

    protected $signature = 'app:notify-finished';

    protected $description = 'Notifies subscribers that an outage is over';

    public function handle(): int
    {
        $events = Event::query()
            ->where('finish_notified', false)
            ->where('finish', '<=', now())
            ->get();

        foreach ($events as $event) {
            // Marked first: a failure while queueing must not repeat the message every minute.
            $event->forceFill(['finish_notified' => true])->save();

            if ($event->finish->lt(now()->subHours(self::MAX_DELAY_HOURS)) || $event->created_at?->gte($event->finish)) {
                continue;
            }

            $count = $event->notifySubscribedFinished();
            $this->info('Event ' . $event->id . ': notified ' . $count);
        }

        return self::SUCCESS;
    }
}
