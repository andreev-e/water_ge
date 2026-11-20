<?php

namespace App\Providers;

use App\Support\SourceStatus;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Longman\TelegramBot\Commands\Command as BotCommand;
use Longman\TelegramBot\Request as BotRequest;
use Longman\TelegramBot\Telegram as BotTelegram;
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
        // Бот Longman без внешней MySQL: команды из app/Telegram/Commands, записи в bot_* делает BotStorage.
        $this->app->singleton(BotTelegram::class, function () {
            // Адрес API задаём до конструктора: Longman создаёт HTTP-клиент с базовым URI уже там.
            $apiUrl = (string) config('services.telegram-bot-api.api_url', '');
            if ($apiUrl !== '') {
                BotRequest::setCustomBotApiUri($apiUrl);
            }

            $bot = new BotTelegram(
                (string) config('services.telegram-bot-api.token'),
                (string) config('services.telegram-bot-api.username'),
            );

            foreach (glob(app_path('Telegram/Commands/*.php')) ?: [] as $file) {
                $class = 'App\\Telegram\\Commands\\' . basename($file, '.php');
                if (is_subclass_of($class, BotCommand::class) && ! (new \ReflectionClass($class))->isAbstract()) {
                    $bot->addCommandClass($class);
                }
            }

            $admins = array_values(array_filter(array_map('trim', explode(',', (string) config('services.telegram-bot-api.admins', '')))));
            if ($admins !== []) {
                $bot->enableAdmins($admins);
            }

            return $bot;
        });

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
