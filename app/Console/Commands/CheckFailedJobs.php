<?php

namespace App\Console\Commands;

use App\Models\FailedJob;
use App\Models\Subscriptions;
use Illuminate\Console\Command;

class CheckFailedJobs extends Command
{
    protected $signature = 'app:check-failed-jobs';

    protected $description = 'Command description';

    /**
     * @throws \JsonException
     */
    public function handle(): void
    {
        $failedJobs = FailedJob::query()->limit(100)->get();

        foreach ($failedJobs as $failedJob) {

            $payload = $failedJob->payload;
            $payload = json_decode($payload, false, 512, JSON_THROW_ON_ERROR);
            $payload = unserialize($payload->data->command);
            $botUserId = $payload->notifiables[0]->routes['telegram'];

            if ($botUserId) {
                Subscriptions::query()
                    ->where('bot_user_id', $botUserId)
                    ->delete();
            }
        }
    }
}
