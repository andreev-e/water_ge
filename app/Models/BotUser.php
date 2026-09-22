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
        if ($botUserId === 6113375921) {
            return;
        }

        Subscriptions::query()
            ->where('bot_user_id', $botUserId)
            ->delete();

        echo $botUserId . PHP_EOL;
        $chat = BotUserChat::query()->where('user_id', $botUserId)->first();
        if ($chat instanceof BotUserChat) {
            DB::statement("DELETE FROM `bot_telegram_update` WHERE `chat_id` = $chat->chat_id");
        }

        // These reference bot_user directly (RESTRICT FKs), so they must be cleared
        // regardless of whether a bot_user_chat row exists for this user — otherwise
        // the final `bot_user` delete below fails with a foreign key violation and
        // the user is silently never removed.
        $callbackQueryQuery = BotCallbackQuery::query()->where('user_id', $botUserId);
        if ($chat instanceof BotUserChat) {
            $callbackQueryQuery->orWhere('chat_id', $chat->chat_id);
        }
        $callbackQueries = $callbackQueryQuery->get();
        foreach ($callbackQueries as $callbackQuery) {
            if ($callbackQuery instanceof BotCallbackQuery) {
                DB::statement("DELETE FROM `bot_telegram_update` WHERE `callback_query_id` = $callbackQuery->id");
                BotCallbackQuery::destroy($callbackQuery->id);
            }
        }

        DB::statement("DELETE FROM `bot_edited_message` WHERE `user_id` = $botUserId");

        $botChatMembersUpdated = BotChatMemberUpdated::query()->where('user_id', $botUserId)->get();
        foreach ($botChatMembersUpdated as $botChatMemberUpdated) {
            if ($botChatMemberUpdated instanceof BotChatMemberUpdated) {
                DB::statement("DELETE FROM `bot_telegram_update` WHERE `my_chat_member_updated_id` = $botChatMemberUpdated->id");
            }
        }
        DB::statement("DELETE FROM `bot_chat_member_updated` WHERE `user_id` = $botUserId");

        DB::statement("DELETE FROM `bot_message` WHERE `reply_to_chat` IS NOT NULL AND `reply_to_message` IS NOT NULL  AND `user_id` = $botUserId");
        DB::statement("DELETE FROM `bot_message` WHERE `user_id` = $botUserId");
        DB::statement("UPDATE `bot_message` SET `forward_from` = NULL WHERE `forward_from` = $botUserId");
        DB::statement("UPDATE `bot_message` SET `via_bot` = NULL WHERE `via_bot` = $botUserId");
        DB::statement("UPDATE `bot_message` SET `left_chat_member` = NULL WHERE `left_chat_member` = $botUserId");

        DB::table('bot_inline_query')->where('user_id', $botUserId)->delete();
        DB::table('bot_chosen_inline_result')->where('user_id', $botUserId)->delete();
        DB::table('bot_pre_checkout_query')->where('user_id', $botUserId)->delete();
        DB::table('bot_shipping_query')->where('user_id', $botUserId)->delete();
        DB::table('bot_chat_join_request')->where('user_id', $botUserId)->delete();
        DB::table('bot_conversation')->where('user_id', $botUserId)->delete();

        if ($chat instanceof BotUserChat) {
            DB::statement("DELETE FROM `bot_callback_query` WHERE `chat_id` = $chat->chat_id");
            DB::statement("DELETE FROM `bot_edited_message` WHERE `chat_id` = $chat->chat_id");
            DB::statement("DELETE FROM `bot_telegram_update` WHERE `chat_id` = $chat->chat_id");
            DB::statement("DELETE FROM `bot_chat_member_updated` WHERE `chat_id` = $chat->chat_id");
            DB::statement("DELETE FROM `bot_message` WHERE `reply_to_chat` IS NOT NULL AND `reply_to_message` IS NOT NULL  AND `chat_id` = $chat->chat_id");
            DB::statement("DELETE FROM `bot_message` WHERE `chat_id` = $chat->chat_id");
            DB::statement("DELETE FROM `bot_chat` WHERE `id` = $chat->chat_id");

            BotUserChat::query()->where('user_id', $botUserId)->delete();
        }

        self::query()->where('id', $botUserId)->delete();
        echo $botUserId . ' deleted' . PHP_EOL;
    }
}
