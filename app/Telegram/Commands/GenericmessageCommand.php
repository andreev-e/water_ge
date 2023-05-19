<?php

namespace App\Telegram\Commands;

use App\Models\Event;
use App\Notifications\EventNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;
use Longman\TelegramBot\Commands\SystemCommand;
use Longman\TelegramBot\Entities\Keyboard;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Telegram;
use stringEncode\Exception;

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

        $events = Event::getCurrent();

        if (count($events)) {
            foreach ($events as $event) {
                Notification::route('telegram', $chatId)
                    ->notify(new EventNotification($event, $languageCode));
            }

            return $this->replyToChat(
                __('telegram.actual_shutdowns', locale: $languageCode) . ' ^^^',
                [
                    'parse_mode' => 'markdown',
                    'reply_markup' => Keyboard::remove(['selective' => true]),
                ]);
        }

        return $this->replyToChat(
            __('telegram.no_shutdowns', locale: $languageCode),
            [
                'parse_mode' => 'markdown',
                'reply_markup' => Keyboard::remove(['selective' => true]),
            ]);
    }
}
