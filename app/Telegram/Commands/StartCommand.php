<?php


namespace App\Telegram\Commands;


use Longman\TelegramBot\Commands\UserCommand;
use Longman\TelegramBot\Entities\Keyboard;
use Longman\TelegramBot\Entities\KeyboardButton;
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

        $keyboard = new Keyboard([
            new KeyboardButton([
                'text' => '/subscribe',
            ])
        ]);

        return $this->replyToChat(
            __('telegram.start', locale: $languageCode),
            [
                'reply_markup' => $keyboard,
            ]
        );
    }
}
