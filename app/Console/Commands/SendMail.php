<?php

namespace App\Console\Commands;

use App\Enums\MailStatuses;
use App\Models\Mail;
use Illuminate\Console\Command;

class SendMail extends Command
{
    protected $signature = 'app:send-mail';

    protected $description = 'Command description';

    public function handle()
    {
        foreach (Mail::query()->where('status', MailStatuses::new)->get() as $mail) {
            $mail->send();
        }
    }
}
