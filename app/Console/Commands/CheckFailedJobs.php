<?php

namespace App\Console\Commands;

use App\Models\BotCallbackQuery;
use App\Models\BotChatMemberUpdated;
use App\Models\BotUser;
use App\Models\BotUserChat;
use App\Models\FailedJob;
use App\Models\Subscriptions;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

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
            if (strpos($failedJob->exception, 'bot was blocked by the user')) {
                $payload = $failedJob->payload;
                $payload = json_decode($payload, false, 512, JSON_THROW_ON_ERROR);
                $payload = unserialize($payload->data->command);
                $botUserId = $payload->notifiables[0]->routes['telegram'];

                if ($botUserId) {
                    Subscriptions::query()
                        ->where('bot_user_id', $botUserId)
                        ->delete();

                    $chat = BotUserChat::query()->where('user_id', $botUserId)->first();
                    if ($chat instanceof BotUserChat) {
                        DB::statement("DELETE FROM `bot_telegram_update` WHERE `chat_id` = $chat->chat_id");

                        $callbackQueries = BotCallbackQuery::query()->where('user_id', $botUserId)->get();
                        foreach ($callbackQueries as $callbackQuery) {
                            if ($callbackQuery instanceof BotCallbackQuery) {
                                DB::statement("DELETE FROM `bot_telegram_update` WHERE `callback_query_id` = $callbackQuery->id");
                                BotCallbackQuery::destroy($callbackQuery->id);
                            }
                        }

                        DB::statement("DELETE FROM `bot_callback_query` WHERE `chat_id` = $chat->chat_id");

                        DB::statement("DELETE FROM `bot_edited_message` WHERE `chat_id` = $chat->chat_id");
                        DB::statement("DELETE FROM `bot_edited_message` WHERE `user_id` = $botUserId");

                        DB::statement("DELETE FROM `bot_message` WHERE `chat_id` = $chat->chat_id");
                        DB::statement("DELETE FROM `bot_message` WHERE `user_id` = $botUserId");

                        BotUserChat::query()->where('user_id', $botUserId)->delete();

                        $botChatMembersUpdated = BotChatMemberUpdated::query()->where('user_id', $botUserId)->get();
                        foreach ($botChatMembersUpdated as $botChatMemberUpdated) {
                            if ($botChatMemberUpdated instanceof BotChatMemberUpdated) {
                                DB::statement("DELETE FROM `bot_telegram_update` WHERE `my_chat_member_updated_id` = $botChatMemberUpdated->id");
                                DB::statement("DELETE FROM `bot_chat_member_updated` WHERE `user_id` = $botUserId");
                            }
                        }

                        BotUser::query()->where('id', $botUserId)->delete();
                        echo $botUserId . ' deleted' . PHP_EOL;
                    }
                }

                $failedJob->delete();
            }
        }
    }
}
