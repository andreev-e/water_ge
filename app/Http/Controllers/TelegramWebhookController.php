<?php

namespace App\Http\Controllers;

use App\Telegram\BotStorage;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Longman\TelegramBot\Telegram;
use Throwable;

class TelegramWebhookController extends Controller
{
    public function __invoke(Request $request, string $token, Telegram $bot, BotStorage $storage): Response
    {
        $expected = (string) config('services.telegram-bot-api.token');
        if ($expected === '' || ! hash_equals($expected, $token)) {
            abort(400);
        }

        try {
            $storage->remember($request->json()->all());
        } catch (Throwable $e) {
            // Запись в bot_* не должна срывать ответ боту: Telegram повторяет доставку при не-2xx
            report($e);
        }

        try {
            $bot->handle();
        } catch (Throwable $e) {
            report($e);
        }

        return response('OK');
    }
}
