<?php

namespace App\Jobs;

use App\Models\Event;
use App\Support\FacebookPost;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class PublishEventToFacebook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 300];

    public function __construct(public Event $event)
    {
    }

    public function handle(): void
    {
        // A retry after a timeout may run after the post actually went through.
        if ($this->event->facebook_post_id) {
            return;
        }

        $this->event->loadMissing(['serviceCenter', 'addresses']);

        $response = Http::asForm()
            ->timeout(30)
            ->post(sprintf(
                'https://graph.facebook.com/%s/%s/feed',
                config('services.facebook.graph_version'),
                config('services.facebook.page_id'),
            ), [
                'message' => FacebookPost::text($this->event),
                'link' => 'https://water.andreev-e.ru/event/' . $this->event->id,
                'access_token' => config('services.facebook.page_token'),
            ])
            ->throw();

        $this->event->forceFill(['facebook_post_id' => $response->json('id')])->save();
    }
}
