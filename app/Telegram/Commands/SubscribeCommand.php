<?php


namespace App\Telegram\Commands;


use Longman\TelegramBot\Commands\UserCommand;
use Longman\TelegramBot\Entities\CallbackQuery;
use Longman\TelegramBot\Entities\InlineKeyboard;
use Longman\TelegramBot\Entities\InlineKeyboardButton;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Exception\TelegramException;

class SubscribeCommand extends UserCommand
{
    protected $name = 'subscribe';
    protected $description = '';
    protected $usage = '/subscribe <serviceCenter>';
    protected $version = '1.0.0';

    public function execute(): ServerResponse
    {
        $languageCode = $this->getMessage()->getFrom()->getLanguageCode();

        $anotherButton = new InlineKeyboardButton([
            'text' => __('telegram.buttons.set_city', locale: $languageCode),
            'callback_data' => 'command=subscribe&serviceCenter=1',
        ]);

        $keyboard = new InlineKeyboard([
            $anotherButton,
        ]);

        return $this->replyToChat(
            __('telegram.start', locale: $languageCode),
            [
                'reply_markup' => $keyboard,
            ]
        );
    }

    public static function handleCallbackQuery(CallbackQuery $callback_query, array $callback_data): ServerResponse
    {
        return $callback_query->answer([
            'text' => 'Awesome ' . json_encode($callback_data),
        ]);
    }
}
