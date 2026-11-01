<?php

namespace Tests\Concerns;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Схема для тестов, которым нужна база.
 *
 * Миграции пакета php-telegram-bot — сырой SQL под MySQL 5.7, который не принимают
 * ни sqlite, ни MySQL 8, поэтому в тестовой базе поднимаем только миграции приложения,
 * а таблицу пользователей бота создаём минимальной заглушкой: по ней считает
 * миграция user_stats и команда снимка статистики.
 *
 * Тесты пишут в базу, поэтому работают только с базой, у которой в имени есть «test»;
 * с рабочей базой из .env они пропускаются, чтобы прогон ничего не испортил.
 */
trait PreparesDatabaseForTests
{
    /** Схема поднимается один раз на процесс: дальше тесты только чистят таблицы. */
    private static bool $schemaReady = false;

    /** Таблицы, которые заполняют тесты: чистим перед каждым тестом. */
    private const TESTED_TABLES = [
        'publication_translations',
        'publications',
        'address_event',
        'events',
        'addresses',
        'service_centers',
        'subscriptions',
        'user_stats',
    ];

    protected function prepareDatabaseForTests(): void
    {
        $database = DB::connection()->getDatabaseName();

        if (!str_contains($database, 'test')) {
            $this->markTestSkipped(
                "Тест пишет данные в базу {$database}: нужна отдельная база с 'test' в имени (см. .env.testing)."
            );
        }

        try {
            DB::connection()->getPdo();
        } catch (Throwable $exception) {
            $this->markTestSkipped('Тестовая база недоступна: ' . $exception->getMessage());
        }

        if (!static::$schemaReady) {
            $this->rebuildSchema();
            static::$schemaReady = true;
        }

        $this->clearTestedTables();
    }

    /** Миграции приложения на пустой схеме: база могла остаться от прерванного прогона. */
    private function rebuildSchema(): void
    {
        DB::connection()->getSchemaBuilder()->dropAllTables();
        $this->createBotUserStub();

        Artisan::call('migrate', ['--path' => 'database/migrations', '--force' => true]);
    }

    /** Заглушка таблицы бота: колонки, которые читают миграция user_stats и снимок статистики. */
    private function createBotUserStub(): void
    {
        if (Schema::hasTable('bot_user')) {
            return;
        }

        Schema::create('bot_user', static function (Blueprint $table) {
            $table->bigInteger('id')->primary();
            $table->boolean('is_bot')->default(false);
            $table->timestamps();
        });
    }

    private function clearTestedTables(): void
    {
        Schema::disableForeignKeyConstraints();

        foreach (self::TESTED_TABLES as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->truncate();
            }
        }

        Schema::enableForeignKeyConstraints();
    }
}
