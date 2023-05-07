<?php

namespace App\Console\Commands;

use App\Models\Address;
use App\Models\Poi;
use App\Models\ServiceCenter;
use App\Services\Translation\TranslationInterface;
use Illuminate\Console\Command;
use Illuminate\Support\LazyCollection;

class Translate extends Command
{
    protected $signature = 'translate:all';

    protected $description = 'Command description';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle(TranslationInterface $translator)
    {
        $elements = ServiceCenter::query()->whereNull('name_en')
            ->limit(100)->cursor();
        $this->translateCollection($elements, $translator);

//        $elements = Address::query()->whereNull('name_en')
//            ->limit(100)->cursor();
//        $this->translateCollection($elements, $translator);
    }

    private function translateCollection(LazyCollection $cursor, TranslationInterface $translator): void
    {
        foreach ($cursor as $element) {
            $element->name_en = $translator->translate($element->name, 'ka_GE', 'en');
//            $element->name_ru = $translator->translate($element->name, 'ka_GE', 'ru');
            $element->timestamps = false;
            $element->save();
            echo $element->id . ' ' . $element->name . ' ' . $element->name_en . PHP_EOL;
        }
    }
}
