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
            $button = new InlineKeyboardButton([
                'text' => $serviceCenter->name_ru,
                'callback_data' => 'command=subscribe&serviceCenter=' . $serviceCenter->id,
            ]);
            $buttons[] = [$button];
        }

        $keyboard = new InlineKeyboard($buttons);

        return $this->replyToChat(
            __('telegram.buttons.set_city', locale: $languageCode),
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
