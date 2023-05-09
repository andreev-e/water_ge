<?php

namespace App\Console\Commands;

use App\Models\Address;
use App\Models\Dictionary;
use App\Models\ServiceCenter;
use App\Services\Translation\TranslationInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use NotificationChannels\Telegram\TelegramUpdates;

class Translate extends Command
{
    protected $signature = 'translate:all';

    protected $description = 'Command description';
    /**
     * @var \Illuminate\Contracts\Foundation\Application|\Illuminate\Foundation\Application|mixed
     */
    private mixed $translator;

    public function __construct()
    {
        $this->translator = app(TranslationInterface::class);
        parent::__construct();
    }

    public function handle()
    {
        $elements = ServiceCenter::query()->whereNull('name_en')
            ->limit(100)->get();
        $this->translateCollection($elements);

        $elements = Address::query()->whereNull('name_en')
            ->limit(100)->get();
        $this->translateCollection($elements);
    }

    private function translateCollection(Collection $cursor): void
    {
        foreach ($cursor as $element) {
            $element->name_en = $this->translatePhrase($element->name, 'en');
            $element->name_ru = $this->translatePhrase($element->name, 'ru');
            $element->timestamps = false;
            $element->save();
            echo $element->id . ' ' . $element->name . ' ' . $element->name_en . PHP_EOL;
        }
    }

    private function translatePhrase(string $phrase, string $toLang): ?string
    {
        return implode(' ', array_map(function($word) use ($toLang) {
            return $this->translateWord($word, $toLang);
        }, explode(' ', $phrase)));
    }

    private function translateWord(string $word, $toLang): ?string
    {
        if (is_numeric($word)) {
            return $word;
        }

        $dictionary = Dictionary::query()->firstOrCreate(['name' => $word]);

        if ($dictionary->name_en === null) {
            $dictionary->name_en = $this->translator->translate($word, 'ka_GE', 'en');
        }
        if ($dictionary->name_ru === null) {
            $dictionary->name_ru = $this->translator->translate($word, 'ka_GE', 'ru');
        }
        $dictionary->save();

        return $dictionary->getAttribute('name_' . $toLang);
    }
}
