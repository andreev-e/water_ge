<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Address extends Model
{
    protected $fillable = [
        'name',
        'name_en',
        'name_ru',
    ];

    public function events(): BelongsToMany
    {
        return $this->belongsToMany(Event::class);
    }

    public function serviceCenter(): BelongsTo
    {
        return $this->belongsTo(ServiceCenter::class);
    }

    public function getTranslitAttribute(): string
    {
        $kartuliLetters = array(
            'ა' => 'a', 'ბ' => 'b', 'გ' => 'g', 'დ' => 'd', 'ე' => 'e', 'ვ' => 'v', 'ზ' => 'z', 'თ' => 't',
            'ი' => 'i', 'კ' => 'k', 'ლ' => 'l', 'მ' => 'm', 'ნ' => 'n', 'ო' => 'o', 'პ' => 'p', 'ჟ' => 'zh',
            'რ' => 'r', 'ს' => 's', 'ტ' => 't', 'უ' => 'u', 'ფ' => 'f', 'ქ' => 'q', 'ღ' => 'gh', 'ყ' => 'kh',
            'შ' => 'sh', 'ჩ' => 'ch', 'ც' => 'ts', 'ძ' => 'dz', 'წ' => 'ts', 'ჭ' => 'ch', 'ხ' => 'kh', 'ჯ' => 'j',
            'ჰ' => 'h',
        );

        $transliteratedText = '';
        $length = mb_strlen($this->name, 'UTF-8');

        for ($i = 0; $i < $length; $i++) {
            $character = mb_substr($this->name, $i, 1, 'UTF-8');
            $transliteratedText .= isset($kartuliLetters[$character]) ? $kartuliLetters[$character] : $character;
        }

        return $transliteratedText;
    }
}
