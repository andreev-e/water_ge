<?php

namespace App\Telegram\Commands;

use App\Models\Event;
use App\Models\ServiceCenter;
use App\Models\Subscriptions;
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
        $languageCode = $this->getMessage()->getFrom()->getLanguageCode();

        $chatId = $this->getMessage()->getChat()->getId();

        $events = Event::getCurrent();

        $cities = ServiceCenter::query()
            ->whereHas('subscriptions', function($query) use ($chatId) {
                $query->where('bot_user_id', $chatId);
            })
            ->get()->pluck('name_ru')->join(', ');

        $totalEvents = 0;
        foreach ($events as $event) {
            $totalEvents += $event->notifySubscribed($chatId);
        }

        if ($totalEvents) {
            return $this->replyToChat(
                __('telegram.you_are_subscribed', ['cities' => $cities], $languageCode) . ' ' .
                __('telegram.actual_shutdowns', locale: $languageCode));

        }

        return $this->replyToChat(
            __('telegram.you_are_subscribed', ['cities' => $cities], $languageCode) . ' ' .
            __('telegram.no_shutdowns', locale: $languageCode),
            [
                'parse_mode' => 'markdown',
                'reply_markup' => Keyboard::remove(['selective' => true]),
            ]);
    }
}
