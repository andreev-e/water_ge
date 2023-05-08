<?php


namespace App\Telegram\Commands;


use Longman\TelegramBot\Commands\UserCommand;
use Longman\TelegramBot\Entities\Keyboard;
use Longman\TelegramBot\Entities\ServerResponse;

class SeachCommand extends UserCommand
{

    /** @var string Command name */
    protected $name = 'search';
    /** @var string Command description */
    protected $description = 'Search';
    /** @var string Usage description */
    protected $usage = '/search';
    /** @var string Version */
    protected $version = '1.0.0';

    /**
     * @throws \Longman\TelegramBot\Exception\TelegramException
     * @throws \JsonException
     */
    public function execute(): ServerResponse
    {
        $languageCode = $this->getMessage()->getFrom()->getLanguageCode();
        $mess = $this->getMessage()->getText(true);

        return $this->replyToChat('Search command: ' . $mess);
    }
}
