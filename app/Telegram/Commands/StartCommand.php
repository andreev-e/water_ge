<?php


namespace App\Telegram\Commands;


use Longman\TelegramBot\Commands\UserCommand;
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

    /**
     * @throws \Longman\TelegramBot\Exception\TelegramException
     */
    public function onCallbackQuery(): ServerResponse
    {
        $callbackQuery = $this->getCallbackQuery();
        $data = $callbackQuery->getData();

        // Handle the callback data
        if ($data === 'set_city') {
            // Code to handle the callback for the '/search' button
            // ...
            // Return the appropriate response
            return $this->replyToChat('You clicked the set_city button!');
        }

        // Handle other callback data if needed

        // Return an empty response if the callback data is not recognized
        return $this->replyToChat('You clicked unknown button!');
    }
}
