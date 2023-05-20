<?php

namespace App\Telegram\Commands;

use Longman\TelegramBot\Commands\SystemCommand;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\Entities\ServerResponse;

class SetCityCommand extends SystemCommand
{
    public function execute(): ServerResponse
    {
        $callback_query = $this->getCallbackQuery();
        $callback_query_id = $callback_query->getId();
        $callback_data = $callback_query->getData();

        $data = [
            'callback_query_id' => $callback_query_id,
            'text' => 'Hello World!',
            'show_alert' => $callback_data === 'thumb up',
            'cache_time' => 5,
        ];

        return Request::answerCallbackQuery($data);
    }
}
