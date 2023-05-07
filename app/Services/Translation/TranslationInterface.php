<?php

namespace App\Services\Translation;

interface TranslationInterface
{
    public function translate(string $string, string $from, string $to): string;
}
