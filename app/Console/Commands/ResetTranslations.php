<?php

namespace App\Console\Commands;

use App\Models\Address;
use App\Models\Dictionary;
use Illuminate\Console\Command;

class ResetTranslations extends Command
{
    protected $signature = 'app:reset-translations';

    protected $description = 'Command description';

    public function handle()
    {
        Address::query()->update([
            'name_en' => null,
            'name_ru' => null,
        ]);

        Dictionary::query()->update([
            'used' => 0,
        ]);
    }
}