<?php


namespace App\Telegram\Commands;


use Longman\TelegramBot\Commands\UserCommand;
use Longman\TelegramBot\Entities\CallbackQuery;
use Longman\TelegramBot\Entities\InlineKeyboard;
use Longman\TelegramBot\Entities\InlineKeyboardButton;
use Longman\TelegramBot\Entities\ServerResponse;

class StartCommand extends UserCommand
{
    protected $name = 'start';
    protected $description = 'Start';
    protected $usage = '/start';
    protected $version = '1.0.0';

    /**
     * @throws \Longman\TelegramBot\Exception\TelegramException
     * @throws \JsonException
     */
    public function execute(): ServerResponse
    {
        $languageCode = $this->getMessage()->getFrom()->getLanguageCode();

        $anotherButton = new InlineKeyboardButton([
            'text' => __('telegram.buttons.set_city', locale: $languageCode),
            'callback_data' => 'command=start&action=set_city',
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
            'text' => 'Awesome' . implode(', ', $callback_data),
        ]);
    }
}
