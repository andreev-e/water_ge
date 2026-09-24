<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Word-by-word machine translation left "сервис Центр" (and some nonsense
 * like "Горла" for Kvareli) in the Russian names shown on the site and in the bot.
 * Georgian `name` stays as is: loaders match sources against it.
 */
return new class extends Migration
{
    private const NAMES = [
        'ყვარელის სერვის ცენტრი' => ['Горла сервис Центр', 'Кварели'],
        'ლაგოდეხის სერვის ცენტრი' => ['Лагодехи сервис Центр', 'Лагодехи'],
        'წნორის სერვის ცენტრი' => ['Цнори сервис Центр', 'Цнори'],
        'სენაკისა და აბაშის გაერთიანებული სერვის ცენტრი' => ['Сенаки И Abash Юнайтед сервис Центр', 'Сенаки и Абаша'],
        'აბაშისა და სენაკის გაერთიანებული სერვის ცენტრი' => ['Абаш И Сенаки Юнайтед сервис Центр', 'Абаша и Сенаки'],
        'ხონის სერვის ცენტრი' => ['Хони сервис Центр', 'Хони'],
        'დმანისის სერვის ცენტრი' => ['Dmanisis сервис Центр', 'Дманиси'],
    ];

    public function up(): void
    {
        foreach (self::NAMES as $name => [$old, $new]) {
            DB::table('service_centers')
                ->where('name', $name)
                ->where('name_ru', 'LIKE', '%сервис%')
                ->update(['name_ru' => $new]);
        }
    }

    public function down(): void
    {
        foreach (self::NAMES as $name => [$old, $new]) {
            DB::table('service_centers')
                ->where('name', $name)
                ->where('name_ru', $new)
                ->update(['name_ru' => $old]);
        }
    }
};
