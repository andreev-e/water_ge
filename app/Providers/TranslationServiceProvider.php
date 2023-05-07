<?php

namespace App\Providers;

use App\Services\Translation\TranslationInterface;
use App\Services\Translation\LingvaNexTranslationService;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;

class TranslationServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(TranslationInterface::class, function () {
            return new LingvaNexTranslationService(config('translate.lingvanex'));
        });
    }

    public function provides(): array
    {
        return [TranslationInterface::class];
    }
}
