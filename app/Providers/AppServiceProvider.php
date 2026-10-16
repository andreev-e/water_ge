<?php

namespace App\Providers;

use App\Support\SourceStatus;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use NotificationChannels\Telegram\Telegram;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Guzzle waits forever by default: one hung request to Telegram froze
        // a queue worker for hours while notifications piled up behind it.
        $this->app->bind(Telegram::class, static fn () => new Telegram(
            config('services.telegram-bot-api.token'),
            new Client(['connect_timeout' => 5, 'timeout' => 20]),
            config('services.telegram-bot-api.base_uri'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);

        View::composer('partial.stats', fn ($view) => $view->with('sources', SourceStatus::all()));
    }
}
