<?php


namespace App\Telegram\Commands;


use Longman\TelegramBot\Commands\UserCommand;
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
                'request_location' => true,
                'request_contact' => false,
                'hide' => false,
                'switch_inline_query_current_chat' => null,
            ]
        );

        $keyboard = new Keyboard(
            [
                'keyboard' => [
                    [
                        '/search',
                        $anotherButton->getRawData(),
                    ],
                ],
                'resize_keyboard' => true,
                'one_time_keyboard' => true,
                'selective' => true,
            ]
        );

        return $this->replyToChat(
            __('telegram.start', locale: $languageCode),
            [
                'parse_mode' => 'markdown',
                'reply_markup' => $keyboard,
            ]);
    }
}
