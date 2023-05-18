<?php

namespace App\Telegram\Commands;

use App\Notifications\EventNotification;
use Illuminate\Support\Facades\Notification;
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

        $chatId = $this->getMessage()->getChat()->getId();

        $callbackQuery = $this->getCallbackQuery();


        if ($callbackQuery) {
            $data = $callbackQuery->getData();

            return $this->replyToChat($data[0],
                [
                    'parse_mode' => 'markdown',
                    'reply_markup' => Keyboard::remove(['selective' => true]),
                ]);
        }

//        Notification::route('telegram', $botUser->id)
//            ->notify(new EventNotification($event, $botUser->language_code));

        return $this->replyToChat(
            __('telegram.default_answer', locale: $languageCode) . ' #' . $chatId ,
            [
                'parse_mode' => 'markdown',
                'reply_markup' => Keyboard::remove(['selective' => true]),
            ]);
    }
}
