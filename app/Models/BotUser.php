<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class BotUser extends Model
{
    protected $table = 'bot_user';

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscriptions::class);
    }

    public static function deleteForever($botUserId): void
    {
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

            self::query()->where('id', $botUserId)->delete();
            echo $botUserId . ' deleted' . PHP_EOL;
        }
    }
}
