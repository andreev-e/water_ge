<?php

namespace App\Console\Commands;

use App\Enums\MailStatuses;
use App\Models\BotUser;
use App\Models\Mail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use PHPUnit\Event\Runtime\PHP;

class MakeMailNotSubscribed extends Command
{
    protected $signature = 'app:make-mail-not-subscribed';
    protected $description = 'Notifies users with 0 subscriptions';

    /**
     * @throws \JsonException
     */
    public function handle()
    {
        $ids = BotUser::query()
            ->withCount('subscriptions')
            ->having('subscriptions_count', 0)
            ->get()
            ->pluck('id');

        $text = __('telegram.mail_not_subscribed', [], 'ru');

        if ($ids->count()) {
//            Mail::query()->create([
//                'text' => $text,
//                'to' => $ids,
//                'status' => MailStatuses::new,
//            ]);

            Mail::query()->create([
                'text' => 'Уведомил не подписанных на рассылку ' . $ids->count(),
                'to' => [411174495],
                'status' => MailStatuses::new,
            ]);

            $deleted = [];

            foreach ($ids as $id) {
                $key = 'mailed_not_subscribed_times_' . $id;
                $value = Cache::get($key, 0);
                $value++;
                Cache::put($key, $value, 60 * 60 * 24 * 25);
                if ($value > 5) {
                    try {
                        BotUser::deleteForever($id);
                        Cache::forget($key);
                        $deleted[$id] = $id;
                    } catch (\Exception $e) {
                        Log::error($e->getMessage());
                    }
                }
            }

            if (count($deleted)) {
                Mail::query()->create([
                    'text' => 'Удалил не подписанных на рассылку: ' . implode(', ', $deleted),
                    'to' => [411174495],
                    'status' => MailStatuses::new,
                ]);
            }
        }
    }
}
