<?php

namespace App\Console\Commands;

use App\Models\BotUser;
use App\Models\FailedJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

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
            Log::error($failedJob->exception);
            if (strpos($failedJob->exception, 'bot was blocked by the user') || strpos($failedJob->exception,
                    'user is deactivated')) {
                $payload = $failedJob->payload;
                $payload = json_decode($payload, false, 512, JSON_THROW_ON_ERROR);
                $payload = unserialize($payload->data->command);
                $botUserId = $payload->notifiables[0]->routes['telegram'];

                if ($botUserId) {
                    BotUser::deleteForever($botUserId);
                }

                $failedJob->delete();
            }
        }
    }
}
