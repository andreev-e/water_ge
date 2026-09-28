<?php

namespace App\Console\Commands;

use App\Enums\MailStatuses;
use App\Models\BotUser;
use App\Models\Mail;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Lang;

/**
 * Queues an arbitrary message for SendMail. The message is either a key of
 * lang/{locale}/telegram.php, sent in each user's language, or plain text.
 */
class Broadcast extends Command
{
    const ADMIN_ID = 411174495;

    protected $signature = 'app:broadcast
        {message : Key of telegram.php (e.g. news_filter) or plain HTML text}
        {--to=all : all, subscribed, not-subscribed or comma-separated chat ids}
        {--test : Send only to the admin, in every language}';

    protected $description = 'Sends a message to bot users';

    public function handle(): int
    {
        $message = $this->argument('message');
        $key = 'telegram.' . $message;
        $isKey = Lang::has($key, 'en', false);

        if ($this->option('test')) {
            $texts = $isKey
                ? collect(['ru', 'en'])->map(fn(string $locale) => __($key, locale: $locale))
                : collect([$message]);
            foreach ($texts as $text) {
                $this->queue($text, [self::ADMIN_ID]);
            }
            $this->info('Queued ' . $texts->count() . ' test message(s) to ' . self::ADMIN_ID);

            return self::SUCCESS;
        }

        $users = $this->recipients();
        if ($users === null) {
            return self::FAILURE;
        }
        if ($users->isEmpty()) {
            $this->warn('No recipients');

            return self::SUCCESS;
        }

        // Unknown locales fall back to English, as everywhere in the bot.
        $groups = $isKey
            ? $users->groupBy(fn(BotUser $user) => __($key, locale: $user->language_code))
            : collect([$message => $users]);

        foreach ($groups as $text => $group) {
            $this->line('<comment>' . $group->count() . ' recipient(s):</comment>');
            $this->line($text);
            $this->newLine();
        }

        if (!$this->confirm('Send to ' . $users->count() . ' user(s)?')) {
            return self::SUCCESS;
        }

        foreach ($groups as $text => $group) {
            $this->queue($text, $group->pluck('id')->all());
        }
        $this->queue('Разослал «' . $message . '»: ' . $users->count(), [self::ADMIN_ID]);
        $this->info('Queued, SendMail will deliver within a minute');

        return self::SUCCESS;
    }

    private function recipients(): ?Collection
    {
        $to = $this->option('to');
        $query = BotUser::query()->select(['id', 'language_code']);

        switch ($to) {
            case 'all':
                break;
            case 'subscribed':
                $query->has('subscriptions');
                break;
            case 'not-subscribed':
                $query->doesntHave('subscriptions');
                break;
            default:
                $ids = array_filter(array_map('trim', explode(',', $to)));
                if (!$ids || array_filter($ids, fn(string $id) => !is_numeric($id))) {
                    $this->error('--to must be all, subscribed, not-subscribed or comma-separated chat ids');

                    return null;
                }
                $query->whereIn('id', $ids);
        }

        return $query->get();
    }

    private function queue(string $text, array $ids): void
    {
        Mail::query()->create([
            'text' => $text,
            'to' => $ids,
            'status' => MailStatuses::new,
        ]);
    }
}
