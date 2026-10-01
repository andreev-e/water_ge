<?php

namespace App\Telegram\Commands;

use App\Console\Commands\Broadcast;
use Longman\TelegramBot\Commands\SystemCommand;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Request;

class SuccessfulpaymentCommand extends SystemCommand
{
    protected $name = 'successfulpayment';

    protected $description = 'Thank for a Telegram Stars payment';

    protected $version = '1.0.0';

    public function execute(): ServerResponse
    {
        $message = $this->getMessage();
        $payment = $message->getSuccessfulPayment();
        $from = $message->getFrom();

        Request::sendMessage([
            'chat_id' => Broadcast::ADMIN_ID,
            'text' => '⭐ ' . $payment->getTotalAmount() . ' от ' . trim($from->getFirstName() . ' ' . $from->getLastName())
                . ($from->getUsername() ? ' @' . $from->getUsername() : '') . ' (' . $from->getId() . ')',
        ]);

        return $this->replyToChat(__('telegram.donate_thanks', locale: $from->getLanguageCode()));
    }
}
