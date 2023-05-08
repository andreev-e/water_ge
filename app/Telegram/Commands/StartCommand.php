<?php


namespace App\Telegram\Commands;


use Longman\TelegramBot\Commands\UserCommand;
use Longman\TelegramBot\Entities\InlineKeyboard;
use Longman\TelegramBot\Entities\Keyboard;
use Longman\TelegramBot\Entities\ServerResponse;

class StartCommand extends UserCommand
{

    /** @var string Command name */
    protected $name = 'start';
    /** @var string Command description */
    protected $description = 'Start';
    /** @var string Usage description */
    protected $usage = '/start';
    /** @var string Version */
    protected $version = '1.0.0';

    /**
     * @throws \Longman\TelegramBot\Exception\TelegramException
     * @throws \JsonException
     */
    public function execute(): ServerResponse
    {
        $languageCode = $this->getMessage()->getFrom()->getLanguageCode();

        return $this->replyToChat(
            __('telegram.start', locale: $languageCode),
            [
                'parse_mode' => 'markdown',
                'reply_markup' => new InlineKeyboard(
                    [
                        'keyboard' => [
                            [
                                [
                                    'text' => __('telegram.buttons.search', locale: $languageCode),
                                    'callback' => 'search',
                                ],
                                __('telegram.buttons.list', locale: $languageCode),
                            ],
                        ],
                        'resize_keyboard' => true,
                        'one_time_keyboard' => true,
                        'selective' => true,
                    ]
                ),
            ]);
    }
}
