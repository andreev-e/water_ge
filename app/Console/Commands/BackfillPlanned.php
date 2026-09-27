<?php

namespace App\Console\Commands;

use App\Enums\EventTypes;
use App\Models\Event;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Console\Command;

/**
 * Marks events stored before the planned flag existed, from the archives of
 * the sources that keep one: water.gov.ge and mygas.ge. Old events are found
 * by type and exact start/finish, and water ones also by the municipality
 * that opens the title; a period two outages of different kinds share is
 * skipped as ambiguous. Energo-pro and GWP publish no history.
 */
class BackfillPlanned extends Command
{
    protected $signature = 'app:backfill-planned {--days=183 : How far back to look} {--dry-run}';

    protected $description = 'Mark old water.gov.ge and gas events as planned or emergency';

    private const GAS_PAGES_LIMIT = 1000;

    public function handle(Client $client): void
    {
        $since = now()->subDays((int) $this->option('days'))->startOfDay();

        $this->mark(EventTypes::water, $this->loadWater($client, $since), $since);
        $this->mark(EventTypes::gas, $this->loadGas($client, $since), $since);
    }

    /**
     * @return array<string, array{place: ?string, planned: bool}[]> "start|finish" => outages in that period
     */
    private function loadWater(Client $client, Carbon $since): array
    {
        $periods = [];
        $page = 1;
        do {
            $response = $client->get('https://water.gov.ge/api/v1/site/planned-works', [
                'query' => [
                    'date_from' => $since->toDateString(),
                    'date_to' => now()->addMonth()->toDateString(),
                    'page' => $page,
                    'page_size' => 100,
                ],
                'timeout' => 60,
            ]);
            $data = json_decode($response->getBody()->getContents(), true);

            foreach ($data['items'] ?? [] as $item) {
                if (($item['source'] ?? null) !== 'problem' || empty($item['published_at']) || empty($item['end_date'])) {
                    continue;
                }
                $key = Carbon::createFromFormat('Y-m-d H:i:s', $item['published_at'])->toDateTimeString()
                    . '|' . Carbon::createFromFormat('Y-m-d H:i:s', $item['end_date'])->toDateTimeString();
                $title = (string) ($item['title'] ?? '');
                $periods[$key][] = [
                    // "გორი - წყალმომარაგების შეწყვეტა ..."; LoadWater matches centers by the name minus its last letter.
                    'place' => mb_substr(trim(explode(' - ', $title)[0]), 0, -1) ?: null,
                    // Same rule as LoadWater.
                    'planned' => !str_contains($title, 'არაგეგმ'),
                ];
            }

            $this->line('water.gov.ge page ' . $page . ' of ' . ceil(($data['total'] ?? 0) / 100));
            $page++;
        } while ($page <= ceil(($data['total'] ?? 0) / 100));

        return $periods;
    }

    /**
     * The gas API ignores the page size and has no date filter, so it is read
     * newest first until a whole page starts before $since.
     *
     * @return array<string, array{place: ?string, planned: bool}[]>
     */
    private function loadGas(Client $client, Carbon $since): array
    {
        $periods = [];
        $page = 1;
        do {
            $response = $client->get('https://utilixwebapi.azurewebsites.net/api/Outage/GetOutagesWithPaging', [
                'query' => ['pageIndex' => $page],
                'headers' => ['Referer' => 'https://www.mygas.ge/'],
                'timeout' => 60,
            ]);
            $data = json_decode($response->getBody()->getContents(), false);

            $recent = false;
            foreach ($data->items ?? [] as $item) {
                // Same parsing as LoadGas, so the keys match what it stored.
                $start = Carbon::createFromFormat('Y-m-d\TH:i:sO', $item->start);
                $finish = Carbon::createFromFormat('Y-m-d\TH:i:sO', $item->end);
                $recent = $recent || $finish->gte($since);
                $periods[$start->toDateTimeString() . '|' . $finish->toDateTimeString()][] = [
                    'place' => null,
                    'planned' => $item->type === 'Planned',
                ];
            }

            if ($page % 20 === 0) {
                $this->line('gas page ' . $page);
            }
            $page++;
            usleep(200_000);
        } while ($recent && ($data->hasNext ?? false) && $page <= self::GAS_PAGES_LIMIT);

        return $periods;
    }

    /**
     * @param array<string, array{place: ?string, planned: bool}[]> $periods
     */
    private function mark(EventTypes $type, array $periods, Carbon $since): void
    {
        $marked = [true => 0, false => 0];
        $ambiguous = 0;
        $unknown = 0;

        Event::query()
            ->with('serviceCenter')
            ->where('type', $type)
            ->where('finish', '>=', $since)
            ->whereNull('external_id')
            ->each(function (Event $event) use ($periods, &$marked, &$ambiguous, &$unknown) {
                if ($event->planned !== null) {
                    return;
                }

                $name = (string) $event->serviceCenter?->name;
                $outages = array_filter(
                    $periods[$event->start->toDateTimeString() . '|' . $event->finish->toDateTimeString()] ?? [],
                    fn($outage) => $outage['place'] === null || str_starts_with($name, $outage['place']),
                );
                $kinds = array_unique(array_column($outages, 'planned'));

                if (count($kinds) !== 1) {
                    $kinds ? $ambiguous++ : $unknown++;
                    return;
                }

                $planned = reset($kinds);
                $marked[$planned]++;

                if (!$this->option('dry-run')) {
                    $event->update(['planned' => $planned]);
                }
            });

        $this->info(sprintf(
            '%s: %d planned, %d emergency, %d ambiguous, %d not found in the source%s',
            $type->value,
            $marked[true],
            $marked[false],
            $ambiguous,
            $unknown,
            $this->option('dry-run') ? ' (dry run)' : '',
        ));
    }
}
