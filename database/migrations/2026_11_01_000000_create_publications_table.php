<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Публикации сайта: недельная и месячная сводки отключений.
 * Период один на тип (unique): неделя начинается в понедельник, месяц — первого числа.
 * statistics — снимок цифр, по которым написан текст: страница рисует по нему таблицы,
 * а после ручной правки текста видно, какие числа в нём были.
 * generated_at — когда сводку собирала команда publications:digest.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('publications', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('type', 8);                       // week | month
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status', 12)->default('draft');   // draft | published
            $table->timestamp('published_at')->nullable();
            $table->json('statistics')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->unique(['type', 'period_start']);
            $table->index(['status', 'period_start']);
        });

        Schema::create('publication_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('publication_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 2);                      // ru | en | ka
            $table->string('title', 160);
            $table->string('excerpt', 400);
            $table->text('body');
            $table->timestamps();

            $table->unique(['publication_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('publication_translations');
        Schema::dropIfExists('publications');
    }
};
