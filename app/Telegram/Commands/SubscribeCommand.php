<?php


namespace App\Telegram\Commands;

use App\Models\ServiceCenter;
use Longman\TelegramBot\Commands\UserCommand;
use Longman\TelegramBot\Entities\CallbackQuery;
use Longman\TelegramBot\Entities\InlineKeyboard;
use Longman\TelegramBot\Entities\InlineKeyboardButton;
use Longman\TelegramBot\Entities\ServerResponse;

class SubscribeCommand extends UserCommand
{
    protected $name = 'subscribe';
    protected $description = '';
    protected $usage = '/subscribe <serviceCenter>';
    protected $version = '1.0.0';

    public function execute(): ServerResponse
    {
        $languageCode = $this->getMessage()->getFrom()->getLanguageCode();

        $buttons = [];
        foreach (ServiceCenter::all() as $serviceCenter) {
            $buttons[] = [
                'text' => $serviceCenter->name_ru,
                'callback_data' => 'command=subscribe&serviceCenter=' . $serviceCenter->id,
            ];
        }

        $keyboard = new InlineKeyboard($buttons);

        $inline_keyboard = new InlineKeyboard([
            ['text' => 'Inline Query (current chat)', 'switch_inline_query_current_chat' => 'inline query...'],
            ['text' => 'Inline Query (other chat)', 'switch_inline_query' => 'inline query...'],
        ], [
            ['text' => 'Callback', 'callback_data' => 'identifier'],
            ['text' => 'Open URL', 'url' => 'https://github.com/php-telegram-bot/example-bot'],
        ]);

        return $this->replyToChat(
            __('telegram.select_city', locale: $languageCode),
            [
                'reply_markup' => $keyboard,
            ]
        );
    }

    public static function handleCallbackQuery(CallbackQuery $callback_query, array $callback_data): ServerResponse
    {
        return $callback_query->answer([
            'text' => json_encode($callback_data),
        ]);
    }
}
