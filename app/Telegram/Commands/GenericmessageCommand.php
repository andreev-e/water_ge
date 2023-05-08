<?php

namespace App\Telegram\Commands;

use App\Models\Location;
use App\Models\Poi;
use Illuminate\Database\Eloquent\Collection;
use Longman\TelegramBot\Commands\SystemCommand;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Telegram;

class GenericmessageCommand extends SystemCommand
{
    protected $name = Telegram::GENERIC_MESSAGE_COMMAND;

    protected $description = 'Handle generic message';

    /**
     * @throws \Longman\TelegramBot\Exception\TelegramException
     */
    public function execute(): ServerResponse
    {
        $location = $this->getMessage()->getLocation();

        $languageCode = $this->getMessage()->getFrom()->getLanguageCode();

        if ($location) {
            $lat = $location->getLatitude();
            $lng = $location->getLongitude();

            $nearest = Poi::nearest($lat, $lng)->limit(10)->get();

            $message = $this->makeMessage($nearest, $languageCode, true);

            return $this->replyToChat($message);
        }

        $text = $this->getMessage()->getText();

        $location = Location::query()->where('name', 'like', '%' . $text . '%')->first();
        if ($location) {
            $points = $location->pois()->limit(10)->get();
            $message = $this->makeMessage($points, $languageCode);
            return $this->replyToChat($message);
        }

        $location = Location::query()->where('name_en', 'like', '%' . $text . '%')->first();
        if ($location) {
            $points = $location->pois()->limit(10)->get();
            $message = $this->makeMessage($points, $languageCode);
            return $this->replyToChat($message);
        }

        return $this->replyToChat(__('telegram.default_answer', locale: $languageCode));
    }


    protected function makeMessage(Collection $pois, $languageCode, $withDist = false): string
    {
        $message = '';
        foreach ($pois as $poi) {
            $message .= $poi->name;
            if ($withDist) {
                $message .= ' (' . round($poi->dist, 1) . ' ' . __('telegram.km', locale: $languageCode) . ') ';
            }
            $message .= PHP_EOL . 'https://altertravel.' . ($languageCode === 'ru' ? 'ru' : 'pro') . '/poi/' . $poi->id . PHP_EOL;
        }
        return $message;
    }
}
