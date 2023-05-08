<?php


namespace App\Telegram\Commands;


use Longman\TelegramBot\Commands\UserCommand;
use Longman\TelegramBot\Entities\InlineKeyboardButton;
use Longman\TelegramBot\Entities\Keyboard;
use Longman\TelegramBot\Entities\ServerResponse;
use PhpTelegramBot\Laravel\Factories\CallbackButton;

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

        $anotherButton = new InlineKeyboardButton(
            [
                'text' => __('telegram.buttons.search', locale: $languageCode),
                'switch_inline_query' => '/search',
            ]
        );

        $keyboard = new Keyboard(
            [
                'keyboard' => [
                    [
                        'NO',
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
