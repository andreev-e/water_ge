<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Webhook бота (Longman без внешней MySQL): путь тот же, что был у php-telegram-bot/laravel.
Route::post('telegram/webhook/{token}', \App\Http\Controllers\TelegramWebhookController::class)
    ->middleware(\PhpTelegramBot\Laravel\Http\Middleware\TrustTelegramNetwork::class)
    ->name('telegram.webhook');
