<?php


namespace App\Telegram\Commands;

use App\Models\ServiceCenter;
use App\Models\Subscriptions;
use Longman\TelegramBot\Commands\UserCommand;
use Longman\TelegramBot\Entities\CallbackQuery;
use Longman\TelegramBot\Entities\InlineKeyboard;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Request;

class SubscribeCommand extends UserCommand
{
    protected $name = 'subscribe';
    protected $description = '';
    protected $usage = '/subscribe <serviceCenter>';
    protected $version = '1.0.0';

    public function execute(): ServerResponse
    {
        $languageCode = $this->getMessage()->getFrom()->getLanguageCode();

        $keyboard = self::makeKeyboard($this);

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

        $messageId = $callback_query->getMessage()->getMessageId();

        $keyboard = self::makeKeyboard($callback_query);

        if ($serviceCenter) {
            $subscription = Subscriptions::query()
                ->where('bot_user_id', $chatId)
                ->where('service_center_id', $serviceCenter->id)
                ->first();

            if ($subscription) {
                $subscription->delete();

                return Request::editMessageText([
                    'chat_id' => $chatId,
                    'message_id' => $messageId,
                    'text' => __('telegram.select_city', locale: $languageCode),
                    'reply_markup' => $keyboard,
                ]);
            }

            Subscriptions::query()->create([
                'bot_user_id' => $chatId,
                'service_center_id' => $callback_data['serviceCenter'],
            ]);

            return Request::editMessageText([
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => __('telegram.select_city', locale: $languageCode),
                'reply_markup' => $keyboard,
            ]);
        }

        return $callback_query->answer([
            'text' => __('telegram.subscribe_fail', locale: $languageCode),
        ]);
    }


    public static function makeKeyboard($command): InlineKeyboard
    {
        $chatId = $command->getMessage()->getChat()->getId();

        $subscribed = Subscriptions::query()->where('bot_user_id', $chatId)
            ->get()->pluck('service_center_id');

        $buttons = [];
        foreach (ServiceCenter::query()->orderBy('name_ru')->get() as $serviceCenter) {
            $buttons[] = [
                [
                    'text' => $serviceCenter->name_ru
                        . (in_array($serviceCenter->id, $subscribed->toArray(), true) ? ' ✅' : ''),
                    'callback_data' => 'command=subscribe&serviceCenter=' . $serviceCenter->id,
                ],
            ];
        }

        return new InlineKeyboard(...$buttons);
    }
}
