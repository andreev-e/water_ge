<?php

use App\Http\Controllers\Controller;
use App\Http\Middleware\SetLocale;
use App\Support\Locale;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

foreach (Locale::WEB as $locale) {
    Route::middleware(SetLocale::class . ':' . $locale)
        ->prefix(Locale::webPrefix($locale))
        ->name($locale === Locale::WEB_DEFAULT ? '' : $locale . '.')
        ->group(function () {
            Route::get('/', [Controller::class, 'index'])->name('index');
            Route::get('/service-centers', [Controller::class, 'serviceCenters'])->name('service-centers');
            Route::get('/addresses', [Controller::class, 'addresses'])->name('addresses');
            Route::get('/addresses/{address}', [Controller::class, 'address'])->name('address');
            Route::get('/event/{event}', [Controller::class, 'event'])->name('event');
        });
}
