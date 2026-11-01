<?php

namespace App\Console\Commands;

use App\Models\Publication;
use App\Services\Publications\DigestPeriod;
use App\Services\Publications\DigestWriter;
use App\Services\Publications\OutageDigest;
use Illuminate\Console\Command;

/**
 * Собирает недельную или месячную сводку отключений: цифры из базы,
 * текст на трёх языках сайта, публикация на сайте. Запускается по расписанию,
 * но можно и руками — за прошлый период или пересобрать уже вышедшую сводку.
 */
class GeneratePublication extends Command
{
    protected $signature = 'publications:digest
        {--type=week : week или month}
        {--period= : для недели 2026-W44 или 2026-10-26, для месяца 2026-10; пусто — прошлый период}
        {--draft : Сохранить черновиком, не показывать на сайте}
        {--force : Перезаписать уже существующую сводку за этот период}
        {--dry-run : Показать цифры и текст, ничего не сохраняя}';

    protected $description = 'Собирает сводку отключений за неделю или месяц и публикует её на сайте';

    public function handle(OutageDigest $digest, DigestWriter $writer): int
    {
        $type = (string) $this->option('type');

        if (!in_array($type, Publication::TYPES, true)) {
            $this->error('Тип сводки: week или month.');

            return self::FAILURE;
        }

        $parsed = DigestPeriod::fromString($type, $this->option('period'));

        if ($parsed === null) {
            $this->error('Период не разобран: для недели — 2026-W44 или 2026-10-26, для месяца — 2026-10.');

            return self::FAILURE;
        }

        $start = DigestPeriod::start($type, $parsed);
        $statistics = $digest->forPeriod($type, $start);
        $slug = DigestPeriod::slug($type, $start);
        $existing = Publication::query()->where('slug', $slug)->first();

        if ($existing && !$this->option('force')) {
            $this->warn("Сводка {$slug} уже есть; перезаписать её можно с --force.");

            return self::SUCCESS;
        }

        $translations = $writer->write($statistics);

        if ($this->option('dry-run')) {
            $this->line(json_encode($statistics, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '');
            foreach ($translations as $locale => $text) {
                $this->line("[{$locale}] {$text['title']}");
                $this->line($text['excerpt']);
                $this->line($text['body']);
            }

            return self::SUCCESS;
        }

        $publication = $existing ?? new Publication(['slug' => $slug]);
        $draft = (bool) $this->option('draft');

        $publication->fill([
            'type' => $type,
            'period_start' => $statistics['period']['start'],
            'period_end' => $statistics['period']['end'],
            'status' => $draft ? Publication::STATUS_DRAFT : Publication::STATUS_PUBLISHED,
            'published_at' => $draft ? null : ($publication->published_at ?? now()),
            'statistics' => $statistics,
            'generated_at' => now(),
        ])->save();

        foreach ($translations as $locale => $text) {
            $publication->translations()->updateOrCreate(['locale' => $locale], $text);
        }

        $this->info(sprintf(
            'Сводка %s: %d отключений, %s, %s.',
            $slug,
            $statistics['outages']['total'],
            $draft ? 'черновик' : 'опубликована',
            $existing ? 'перезаписана' : 'создана',
        ));

        return self::SUCCESS;
    }
}
