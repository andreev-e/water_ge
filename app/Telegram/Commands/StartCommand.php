<?php


namespace App\Telegram\Commands;


use Longman\TelegramBot\Commands\UserCommand;
use Longman\TelegramBot\Entities\InlineKeyboard;
use Longman\TelegramBot\Entities\InlineKeyboardButton;
use Longman\TelegramBot\Entities\ServerResponse;

class StartCommand extends UserCommand
{
    protected static array $callbacks = ['set_city'];
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
            'callback_data' => 'set_city',
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
}
