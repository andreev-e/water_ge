<?php

namespace App\Telegram\Commands;

use Longman\TelegramBot\Commands\SystemCommand;
use Longman\TelegramBot\Entities\ServerResponse;

class CallbackqueryCommand extends SystemCommand
{

    protected $name = 'callbackquery';

    protected $description = 'Reply to callback query';

    protected $version = '0.2.0';

    public function execute(): ServerResponse
    {
        $callback_query = $this->getCallbackQuery();
        parse_str($callback_query->getData(), $callback_data);

        return match ($callback_data['command'] ?? null) {
            'subscribe' => SubscribeCommand::handleCallbackQuery($callback_query, $callback_data),
            default => $callback_query->answer(),
        };
    }
}
