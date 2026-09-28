<?php

namespace App\Telegram\Commands;

use App\Models\Event;
use App\Models\ServiceCenter;
use Longman\TelegramBot\Commands\SystemCommand;
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

        $filterReply = FilterCommand::handleReply($chatId, (string)$this->getMessage()->getText(), $languageCode);
        if ($filterReply !== null) {
            return $this->replyToChat($filterReply);
        }

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
                __('telegram.change_subscriptions', locale: $languageCode) . ' ' .
                __('telegram.change_filter', locale: $languageCode));
        }

        return $this->replyToChat(
            __('telegram.you_are_subscribed', ['cities' => $cities], $languageCode) . ' ' .
            __('telegram.no_shutdowns', locale: $languageCode));
    }
}
