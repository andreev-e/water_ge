<?php

namespace App\Telegram\Commands;

use Longman\TelegramBot\Commands\SystemCommand;
use Longman\TelegramBot\Entities\ServerResponse;

class PrecheckoutqueryCommand extends SystemCommand
{
    protected $name = 'precheckoutquery';

    protected $description = 'Confirm a Telegram Stars payment';

    protected $version = '1.0.0';

    public function execute(): ServerResponse
    {
        // Telegram cancels the payment unless it is confirmed within 10 seconds.
        return $this->getPreCheckoutQuery()->answer(true);
    }
}
