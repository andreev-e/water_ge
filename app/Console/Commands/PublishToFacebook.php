<?php

namespace App\Console\Commands;

use App\Jobs\PublishEventToFacebook;
use App\Models\Event;
use App\Support\FacebookPost;
use Illuminate\Console\Command;

class PublishToFacebook extends Command
{
    protected $signature = 'facebook:publish {event? : Event id; defaults to the nearest current event not yet posted} {--dry-run : Print the post text without publishing}';

    protected $description = 'Publish one event to the Facebook page right away (for checking the page setup)';

    public function handle(): int
    {
        $event = $this->argument('event')
            ? Event::query()->find($this->argument('event'))
            : Event::query()->current()->whereNull('facebook_post_id')->first();

        if (!$event) {
            $this->error('Event not found');
            return self::FAILURE;
        }

        if ($event->facebook_post_id) {
            $this->error('Event ' . $event->id . ' is already posted: ' . $event->facebook_post_id);
            return self::FAILURE;
        }

        $event->loadMissing(['serviceCenter', 'addresses']);
        $this->line(FacebookPost::text($event));
        $this->newLine();

        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }

        if (!config('services.facebook.page_id') || !config('services.facebook.page_token')) {
            $this->error('FACEBOOK_PAGE_ID and FACEBOOK_PAGE_TOKEN must be set');
            return self::FAILURE;
        }

        PublishEventToFacebook::dispatchSync($event);

        $this->info('Published event ' . $event->id . ' as ' . $event->refresh()->facebook_post_id);

        return self::SUCCESS;
    }
}
