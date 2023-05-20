<?php


namespace App\Telegram\Commands;


use Longman\TelegramBot\Commands\UserCommand;
use Longman\TelegramBot\Entities\CallbackQuery;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Exception\TelegramException;

class SubscribeCommand extends UserCommand
{
    protected $name = 'subscribe';
    protected $description = '';
    protected $usage = '/subscribe';
    protected $version = '1.0.0';

    public function execute(): ServerResponse
    {
        return $this->replyToChat('subscribe');
    }

    public static function handleCallbackQuery(CallbackQuery $callback_query, array $callback_data): ServerResponse
    {
        return $callback_query->answer([
            'text' => 'Awesome' . implode(', ', $callback_data),
        ]);
    }
}
