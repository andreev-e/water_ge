<?php


namespace App\Telegram\Commands;

use App\Models\ServiceCenter;
use App\Models\Subscriptions;
use Longman\TelegramBot\Commands\UserCommand;
use Longman\TelegramBot\Entities\CallbackQuery;
use Longman\TelegramBot\Entities\InlineKeyboard;
use Longman\TelegramBot\Entities\InlineKeyboardButton;
use Longman\TelegramBot\Entities\ServerResponse;

class SubscribeCommand extends UserCommand
{
    protected $name = 'subscribe';
    protected $description = '';
    protected $usage = '/subscribe <serviceCenter>';
    protected $version = '1.0.0';

    public function execute(): ServerResponse
    {
        $languageCode = $this->getMessage()->getFrom()->getLanguageCode();

        $buttons = [];
        foreach (ServiceCenter::query()->orderByDesc('total_addresses')->get() as $serviceCenter) {
            $buttons[] = [
                [
                    'text' => $serviceCenter->name_ru,
                    'callback_data' => 'command=subscribe&serviceCenter=' . $serviceCenter->id,
                ],
            ];
        }

        $keyboard = new InlineKeyboard(...$buttons);

        return $this->replyToChat(
            __('telegram.select_city', locale: $languageCode),
            [
                'reply_markup' => $keyboard,
            ]
        );
    }

    public static function handleCallbackQuery(CallbackQuery $callback_query, array $callback_data): ServerResponse
    {
        $languageCode = $callback_query->getMessage()->getFrom()->getLanguageCode();

        $chatId = $callback_query->getMessage()->getChat()->getId();

        $serviceCenter = ServiceCenter::query()->find($callback_data['serviceCenter']);

        if ($serviceCenter) {
            $subscription = Subscriptions::query()
                ->where('bot_user_id', $chatId)
                ->where('service_center_id', $serviceCenter->id);

            if ($subscription) {
                $subscription->delete();
            } else {
                Subscriptions::query()->create([
                    'bot_user_id' => $chatId,
                    'service_center_id' => $callback_data['serviceCenter'],
                ]);
            }

            return $callback_query->answer([
                'text' => __('telegram.subscribe_success', ['city' => $serviceCenter->name_ru], $languageCode),
            ]);
        }

    }
}
