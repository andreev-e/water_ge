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
            ->where('username', 'evgeniy_planer')
            ->withCount('subscriptions')
            ->having('subscriptions_count', 0)
            ->get()
            ->pluck('id');
        if ($ids->count()) {
            Mail::query()->create([
                'text' => 'Вы не подписались ни на один город. Пожалуйста, нажмите /subscribe и выберите нужные на города, чтобы получать уведомления о плановых отключениях.',
                'to' => $ids,
                'status' => MailStatuses::new,
            ]);
        }
    }
}
