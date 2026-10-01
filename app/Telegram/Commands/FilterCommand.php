<?php


namespace App\Telegram\Commands;

use App\Models\Address;
use App\Models\Subscriptions;
use Illuminate\Support\Facades\Cache;
use Longman\TelegramBot\Commands\UserCommand;
use Longman\TelegramBot\Entities\CallbackQuery;
use Longman\TelegramBot\Entities\InlineKeyboard;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Request;

/**
 * Street filter of a subscription: the user picks a service center here, and
 * the next plain message becomes its filter (see GenericmessageCommand).
 */
class FilterCommand extends UserCommand
{
    protected $name = 'filter';
    protected $description = '';
    protected $usage = '/filter';
    protected $version = '1.0.0';

    const CLEAR = '-';
    const SHOW_MATCHES = 20;

    public function execute(): ServerResponse
    {
        $languageCode = $this->getMessage()->getFrom()->getLanguageCode();
        $chatId = $this->getMessage()->getChat()->getId();

        $subscriptions = Subscriptions::query()
            ->with('serviceCenter')
            ->where('bot_user_id', $chatId)
            ->get()
            ->sortBy(fn(Subscriptions $subscription) => $subscription->serviceCenter->localizedName($languageCode));

        if ($subscriptions->isEmpty()) {
            return $this->replyToChat(__('telegram.filter_no_subscriptions', locale: $languageCode));
        }

        $buttons = [];
        foreach ($subscriptions as $subscription) {
            $buttons[] = [
                [
                    'text' => $subscription->serviceCenter->localizedName($languageCode)
                        . ($subscription->street_filter ? ': ' . $subscription->street_filter : ''),
                    'callback_data' => 'command=filter&serviceCenter=' . $subscription->service_center_id,
                ],
            ];
        }

        return $this->replyToChat(
            __('telegram.filter_select_city', locale: $languageCode),
            [
                'reply_markup' => new InlineKeyboard(...$buttons),
            ]
        );
    }

    public static function handleCallbackQuery(CallbackQuery $callback_query, array $callback_data): ServerResponse
    {
        $languageCode = $callback_query->getFrom()->getLanguageCode();
        $chatId = $callback_query->getMessage()->getChat()->getId();

        $subscription = Subscriptions::query()
            ->with('serviceCenter')
            ->where('bot_user_id', $chatId)
            ->where('service_center_id', $callback_data['serviceCenter'] ?? null)
            ->first();

        if (!$subscription) {
            return $callback_query->answer([
                'text' => __('telegram.subscribe_fail', locale: $languageCode),
            ]);
        }

        Cache::put(self::pendingKey($chatId), $subscription->id, now()->addMinutes(30));
        $callback_query->answer();

        return Request::sendMessage([
            'chat_id' => $chatId,
            'text' => __('telegram.filter_enter_streets', [
                'city' => $subscription->serviceCenter->localizedName($languageCode),
                'current' => $subscription->street_filter ?: __('telegram.filter_none', locale: $languageCode),
                'clear' => self::CLEAR,
            ], $languageCode),
        ]);
    }

    /**
     * Saves the message as the filter when the user has just picked a service
     * center in /filter; null when the message isn't a filter answer.
     */
    public static function handleReply(int $chatId, string $text, ?string $languageCode): ?string
    {
        // A sticker or photo shouldn't wipe the filter.
        if (trim($text) === '') {
            return null;
        }

        $subscriptionId = Cache::pull(self::pendingKey($chatId));
        if (!$subscriptionId) {
            return null;
        }

        $subscription = Subscriptions::query()
            ->with('serviceCenter')
            ->where('bot_user_id', $chatId)
            ->find($subscriptionId);

        if (!$subscription) {
            return __('telegram.subscribe_fail', locale: $languageCode);
        }

        $terms = trim($text) === self::CLEAR ? [] : Subscriptions::parseStreetFilter($text);
        $subscription->update(['street_filter' => $terms ? mb_substr(implode(', ', $terms), 0, 255) : null]);

        if (!$terms) {
            return __('telegram.filter_cleared', ['city' => $subscription->serviceCenter->localizedName($languageCode)], $languageCode);
        }

        return __('telegram.filter_saved', ['city' => $subscription->serviceCenter->localizedName($languageCode), 'streets' => $subscription->street_filter], $languageCode)
            . "\n\n" . self::describeMatches($subscription, $terms, $languageCode);
    }

    /**
     * Addresses of the service center's past outages that the filter catches,
     * so a misspelled street shows up right away.
     */
    private static function describeMatches(Subscriptions $subscription, array $terms, ?string $languageCode): string
    {
        $matches = $subscription->serviceCenter->addresses()
            ->get(['id', 'name'])
            ->filter(fn(Address $address) => Subscriptions::containsAny($address->name . ' ' . $address->translit, $terms))
            ->map(fn(Address $address) => $address->localizedName($languageCode))
            ->unique()
            ->sort()
            ->values();

        if ($matches->isEmpty()) {
            return __('telegram.filter_no_matches', locale: $languageCode);
        }

        $text = __('telegram.filter_matches', ['addresses' => $matches->take(self::SHOW_MATCHES)->implode("\n")], $languageCode);
        if ($matches->count() > self::SHOW_MATCHES) {
            $text .= "\n" . __('telegram.filter_matches_more', ['count' => $matches->count() - self::SHOW_MATCHES], $languageCode);
        }

        return $text;
    }

    private static function pendingKey(int $chatId): string
    {
        return 'street_filter_pending_' . $chatId;
    }
}
