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
        foreach (ServiceCenter::all()->chunk(2) as $chunk) {
            $chunkButtons = [];
            foreach ($chunk as $serviceCenter) {
                $chunkButtons[] = new InlineKeyboardButton([
                    'text' => $serviceCenter->name_ru,
                    'callback_data' => 'command=subscribe&serviceCenter=' . $serviceCenter->id,
                ]);
            }
            $buttons[] = $chunkButtons;
        }

        $keyboard = new InlineKeyboard($buttons);

        $keyboard->setSelective(true);
        $keyboard->setResizeKeyboard(true);

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
