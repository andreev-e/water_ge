<?php

namespace App\Telegram\Commands;

use Longman\TelegramBot\Entities\ServerResponse;

class MessageCommand extends GenericmessageCommand
{
    public function execute(): ServerResponse
    {
        $message = $this->getMessage();

        $message_text = $message->getText(true);

        return $this->replyToChat($message_text);
    }
}
