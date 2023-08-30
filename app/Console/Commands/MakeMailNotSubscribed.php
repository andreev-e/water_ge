<?php

namespace App\Console\Commands;

use App\Enums\MailStatuses;
use App\Models\BotUser;
use App\Models\Mail;
use Illuminate\Console\Command;

class MakeMailNotSubscribed extends Command
{
    protected $signature = 'app:make-mail-not-subscribed';
    protected $description = 'Notifies users with 0 subscriptions';

    public function handle()
    {
        $ids = BotUser::query()
            ->withCount('subscriptions')
            ->having('subscriptions_count', 0)
            ->get()
            ->pluck('id');

        $text = __('telegram.mail_not_subscribed', [], 'ru');

        if ($ids->count()) {
            Mail::query()->create([
                'text' => $text,
                'to' => $ids,
                'status' => MailStatuses::new,
            ]);
        }

        $ids = BotUser::query()
            ->where('username', 'evgeniy_planer')
            ->get()
            ->pluck('id');
        if ($ids->count()) {
            Mail::query()->create([
                'text' => 'Check  ' . $text,
                'to' => $ids,
                'status' => MailStatuses::new,
            ]);
        }
    }
}
