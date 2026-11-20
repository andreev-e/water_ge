<?php

namespace App\Telegram;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Запись пользователей и чатов апдейта в таблицы bot_* (PostgreSQL).
 * Раньше это делала библиотека Longman через внешнее MySQL-соединение;
 * приложение читает эти таблицы (рассылки, подписки, статистика), поэтому
 * строки пишем сами до запуска команд.
 */
class BotStorage
{
    /**
     * @param array<string, mixed> $update тело апдейта из webhook
     */
    public function remember(array $update): void
    {
        $from = $this->firstArray($update, [
            'message.from', 'edited_message.from', 'channel_post.from', 'callback_query.from',
            'inline_query.from', 'chosen_inline_result.from', 'pre_checkout_query.from',
            'shipping_query.from', 'poll_answer.user', 'my_chat_member.from', 'chat_member.from',
            'chat_join_request.from', 'chat_member_updated.from',
        ]);
        $chat = $this->firstArray($update, [
            'message.chat', 'edited_message.chat', 'channel_post.chat', 'callback_query.message.chat',
            'edited_channel_post.chat', 'my_chat_member.chat', 'chat_member.chat', 'chat_join_request.chat',
        ]);

        $now = CarbonImmutable::now();

        if ($from !== null && isset($from['id'])) {
            $this->upsertUser($from, $now);
        }

        if ($chat !== null && isset($chat['id'])) {
            $this->upsertChat($chat, $now);
        }

        if ($from !== null && $chat !== null && isset($from['id'], $chat['id'])) {
            $this->linkUserToChat((int) $from['id'], (int) $chat['id']);
        }
    }

    /**
     * @param array<string, mixed> $from
     */
    private function upsertUser(array $from, CarbonImmutable $now): void
    {
        DB::table('bot_user')->upsert([[
            'id' => (int) $from['id'],
            'is_bot' => (bool) ($from['is_bot'] ?? false),
            'first_name' => (string) ($from['first_name'] ?? ''),
            'last_name' => $from['last_name'] ?? null,
            'username' => $from['username'] ?? null,
            'language_code' => $from['language_code'] ?? null,
            'is_premium' => (bool) ($from['is_premium'] ?? false),
            'added_to_attachment_menu' => (bool) ($from['added_to_attachment_menu'] ?? false),
            'created_at' => $now,
            'updated_at' => $now,
        ]], ['id'], ['is_bot', 'first_name', 'last_name', 'username', 'language_code', 'is_premium', 'added_to_attachment_menu', 'updated_at']);
    }

    /**
     * @param array<string, mixed> $chat
     */
    private function upsertChat(array $chat, CarbonImmutable $now): void
    {
        DB::table('bot_chat')->upsert([[
            'id' => (int) $chat['id'],
            'type' => (string) ($chat['type'] ?? 'private'),
            'title' => (string) ($chat['title'] ?? ''),
            'username' => $chat['username'] ?? null,
            'first_name' => $chat['first_name'] ?? null,
            'last_name' => $chat['last_name'] ?? null,
            'is_forum' => (bool) ($chat['is_forum'] ?? false),
            'all_members_are_administrators' => (bool) ($chat['all_members_are_administrators'] ?? false),
            'created_at' => $now,
            'updated_at' => $now,
        ]], ['id'], ['type', 'title', 'username', 'first_name', 'last_name', 'is_forum', 'all_members_are_administrators', 'updated_at']);
    }

    private function linkUserToChat(int $userId, int $chatId): void
    {
        $exists = DB::table('bot_user_chat')->where('user_id', $userId)->where('chat_id', $chatId)->exists();
        if (! $exists) {
            DB::table('bot_user_chat')->insert(['user_id' => $userId, 'chat_id' => $chatId]);
        }
    }

    /**
     * @param array<string, mixed> $update
     * @param list<string> $paths
     * @return array<string, mixed>|null
     */
    private function firstArray(array $update, array $paths): ?array
    {
        foreach ($paths as $path) {
            $value = data_get($update, $path);
            if (is_array($value)) {
                return $value;
            }
        }

        return null;
    }
}
