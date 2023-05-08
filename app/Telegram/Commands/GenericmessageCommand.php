<?php

namespace App\Telegram\Commands;

use Longman\TelegramBot\Commands\SystemCommand;
use Longman\TelegramBot\Entities\Keyboard;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Telegram;

class GenericmessageCommand extends SystemCommand
{
    protected $name = Telegram::GENERIC_MESSAGE_COMMAND;

    protected $description = 'Handle generic message';

    /**
     * @throws \Longman\TelegramBot\Exception\TelegramException
     */
    public function execute(): ServerResponse
    {
        $location = $this->getMessage()->getLocation();

        $languageCode = $this->getMessage()->getFrom()->getLanguageCode();

        $text = $this->getMessage()->getText();

        return $this->replyToChat(__('telegram.default_answer', locale: $languageCode),[
            'parse_mode' => 'markdown',
            'reply_markup' => Keyboard::remove(['selective' => true]),
        ]);
    }
}
