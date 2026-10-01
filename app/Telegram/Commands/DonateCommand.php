<?php

namespace App\Telegram\Commands;

use Longman\TelegramBot\Commands\UserCommand;
use Longman\TelegramBot\Entities\CallbackQuery;
use Longman\TelegramBot\Entities\InlineKeyboard;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Request;

/**
 * Donations in Telegram Stars: no payment provider is needed, the bot only
 * sends an invoice in XTR and confirms the pre-checkout query.
 * Stars invoices have no tips, so the amount is picked before the invoice.
 */
class DonateCommand extends UserCommand
{
    const AMOUNTS = [10, 50, 100, 500];

    protected $name = 'donate';
    protected $description = 'Support the bot with Telegram Stars';
    protected $usage = '/donate';
    protected $version = '1.1.0';

    public function execute(): ServerResponse
    {
        $message = $this->getMessage();

        return self::sendChoice($message->getChat()->getId(), $message->getFrom()->getLanguageCode());
    }

    public static function handleCallbackQuery(CallbackQuery $callback_query, array $callback_data): ServerResponse
    {
        $callback_query->answer();

        $chatId = $callback_query->getMessage()->getChat()->getId();
        $languageCode = $callback_query->getFrom()->getLanguageCode();
        $stars = (int) ($callback_data['stars'] ?? 0);

        return in_array($stars, self::AMOUNTS, true)
            ? self::sendInvoice($chatId, $languageCode, $stars)
            : self::sendChoice($chatId, $languageCode);
    }

    public static function sendChoice(int $chatId, ?string $languageCode): ServerResponse
    {
        $buttons = array_map(fn(int $stars) => [
            'text' => $stars . ' ⭐',
            'callback_data' => http_build_query(['command' => 'donate', 'stars' => $stars]),
        ], self::AMOUNTS);

        return Request::sendMessage([
            'chat_id' => $chatId,
            'text' => __('telegram.donate_choose', locale: $languageCode),
            'reply_markup' => new InlineKeyboard($buttons),
        ]);
    }

    public static function sendInvoice(int $chatId, ?string $languageCode, int $stars): ServerResponse
    {
        return Request::sendInvoice([
            'chat_id' => $chatId,
            'title' => __('telegram.donate_title', locale: $languageCode),
            'description' => __('telegram.donate_description', locale: $languageCode),
            'payload' => 'donate',
            'currency' => 'XTR',
            'prices' => [['label' => __('telegram.donate_title', locale: $languageCode), 'amount' => $stars]],
        ]);
    }
}
