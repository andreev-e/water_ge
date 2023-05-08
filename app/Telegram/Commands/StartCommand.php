<?php


namespace App\Telegram\Commands;


use Longman\TelegramBot\Commands\UserCommand;
use Longman\TelegramBot\Entities\InlineQuery;
use Longman\TelegramBot\Entities\Keyboard;
use Longman\TelegramBot\Entities\KeyboardButton;
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

        $anotherButton = new KeyboardButton(
            [
                'text' => __('telegram.buttons.search', locale: $languageCode),
                'callback_data' => '/search',
            ]
        );

        $keyboard = new Keyboard(
            [
                'keyboard' => [
                    [
                        $anotherButton->getRawData(),
                    ],
                ],
                'resize_keyboard' => true,
                'one_time_keyboard' => false,
                'selective' => true,
            ]
        );

        return $this->replyToChat(
            __('telegram.start', locale: $languageCode) . '<button>test</button>',
            [
                'parse_mode' => 'html',
                'reply_markup' => $keyboard,
            ]);
    }
}
