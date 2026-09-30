<?php

namespace App\Console\Commands;

use App\Jobs\PublishEventToFacebook;
use App\Models\Event;
use Illuminate\Console\Command;
use Illuminate\Http\Client\RequestException;

class PublishPendingToFacebook extends Command
{
    protected $signature = 'facebook:publish-pending';

    protected $description = 'Publish events waiting for Facebook, earliest start first';

    public function handle(): int
    {
        Event::query()
            ->where('facebook_pending', true)
            ->where('finish', '<', now())
            ->update(['facebook_pending' => false]);

        $events = Event::query()
            ->where('facebook_pending', true)
            ->orderBy('start')
            ->orderBy('id')
            ->get();

        foreach ($events as $event) {
            try {
                // Synchronously, so the posts go out strictly in this order.
                PublishEventToFacebook::dispatchSync($event);
            } catch (RequestException $e) {
                report($e);
                if ($e->response->serverError()) {
                    // Facebook is down: keep the rest pending for the next run.
                    return self::FAILURE;
                }
                // Facebook rejected this post, retrying will not help.
            }

            $event->forceFill(['facebook_pending' => false])->save();
        }

        return self::SUCCESS;
    }
}
