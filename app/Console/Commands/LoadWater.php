<?php

namespace App\Console\Commands;

use App\Enums\EventTypes;
use App\Models\Address;
use App\Models\Event;
use App\Models\ServiceCenter;
use App\Support\SourceStatus;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Console\Command;

class LoadWater extends Command
{
    protected $signature = 'load-schedule {--from= : Import the archive since this date, without notifying anyone}';

    protected $description = 'Load water schedule';

    private const BORJOMI = 'ბორჯომის სერვის ცენტრი';

    private const BAKURIANI = 'ბაკურიანის სერვის ცენტრი';

    /**
     * Contractors whose bare name doesn't prefix their center's: Georgian
     * declension drops a vowel (გარდაბანი -> გარდაბნის), and one contractor
     * covers the whole Borjomi municipality.
     */
    private const CONTRACTOR_CENTERS = [
        'გარდაბანი' => 'გარდაბნის სერვის ცენტრი',
        'ბორჯომი დ. ბაკურიანი' => self::BORJOMI,
    ];

    /**
     * water.gov.ge is now a Nuxt SPA; the old #accordion HTML markup is gone.
     * The planned-works list is embedded as the page's Nuxt SSR payload instead.
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function handle(Client $client): void
    {
        if ($this->option('from')) {
            $this->importArchive($client, Carbon::parse($this->option('from')));
            return;
        }

        $response = $client->get('https://water.gov.ge/planned-works');

        if (!preg_match('/<script[^>]*id="__NUXT_DATA__"[^>]*>(.*?)<\/script>/s', $response->getBody()->getContents(), $matches)) {
            $this->error('Could not find __NUXT_DATA__ payload');
            return;
        }

        $payload = json_decode($matches[1], true);

        if (!is_array($payload)) {
            $this->error('Could not decode __NUXT_DATA__ payload');
            return;
        }

        foreach ($this->extractProblems($payload) as $item) {
            $this->store($item, true);
        }

        SourceStatus::markUpdated(SourceStatus::WATER);
    }

    /**
     * The page only renders the latest items; the site's JSON API behind it
     * pages through the whole archive by date.
     */
    private function importArchive(Client $client, Carbon $from): void
    {
        $page = 1;
        do {
            $response = $client->get('https://water.gov.ge/api/v1/site/planned-works', [
                'query' => [
                    'date_from' => $from->toDateString(),
                    'date_to' => now()->toDateString(),
                    'page' => $page,
                    'page_size' => 100,
                ],
                'timeout' => 60,
            ]);
            $data = json_decode($response->getBody()->getContents(), true);
            $pages = (int) ceil(($data['total'] ?? 0) / 100);

            $created = 0;
            foreach ($data['items'] ?? [] as $item) {
                $created += (int) $this->store($item, false);
            }

            $this->line('page ' . $page . ' of ' . $pages . ': ' . $created . ' new');
            $page++;
        } while ($page <= $pages);
    }

    /**
     * @return bool whether a new event was created
     */
    private function store(array $item, bool $notify): bool
    {
        if (($item['source'] ?? null) !== 'problem' || empty($item['published_at']) || empty($item['end_date'])) {
            return false;
        }

        $serviceCenterName = trim((string) ($item['contractor'] ?? ''));

        if ($serviceCenterName === '') {
            return false;
        }

        $addresses = $this->extractAddresses((string) ($item['excerpt'] ?? ''));
        $serviceCenter = $this->resolveServiceCenter($serviceCenterName, $addresses);

        $start = Carbon::createFromFormat('Y-m-d H:i:s', $item['published_at']);
        $finish = Carbon::createFromFormat('Y-m-d H:i:s', $item['end_date']);
        // Titles end with "გეგმური სამუშაოების გამო" or "არაგეგმური სამუშაოების გამო".
        $planned = !str_contains((string) ($item['title'] ?? ''), 'არაგეგმ');

        $event = Event::query()
            ->where('service_center_id', $serviceCenter->id)
            ->where('start', $start)
            ->where('finish', $finish)
            ->where('type', EventTypes::water)
            ->first();

        if (!$event) {
            /* @var $event Event */
            $event = Event::query()->create([
                'service_center_id' => $serviceCenter->id,
                'start' => $start,
                'finish' => $finish,
                'total_addresses' => count($addresses),
                'type' => EventTypes::water,
                'planned' => $planned,
            ]);

            $this->attachAddresses($serviceCenter, $event, $addresses);

            if ($notify) {
                $event->notifySubscribed();
                $event->publishToFacebook();
            }

            return true;
        }

        if ($event->planned === null) {
            $event->update(['planned' => $planned]);
        }

        return false;
    }

    /**
     * A few queries per event rather than a few per address: the archive
     * import creates thousands of events against a remote database.
     */
    private function attachAddresses(ServiceCenter $serviceCenter, Event $event, array $addresses): void
    {
        $names = array_values(array_unique($addresses));

        if (!$names) {
            return;
        }

        $ids = fn() => $serviceCenter->addresses()->whereIn('name', $names)->pluck('id', 'name');
        $existing = $ids();
        $missing = array_diff($names, $existing->keys()->all());

        if ($missing) {
            $now = now();
            Address::query()->insert(array_map(fn($name) => [
                'service_center_id' => $serviceCenter->id,
                'name' => $name,
                'created_at' => $now,
                'updated_at' => $now,
            ], array_values($missing)));
            $existing = $ids();
        }

        $event->addresses()->syncWithoutDetaching($existing->values()->all());
    }

    /**
     * Resolves the "planned-works-problems" list of items out of the Nuxt
     * __NUXT_DATA__ payload, which stores every value as an index into the
     * flat $data array (devalue format).
     */
    private function extractProblems(array $data): array
    {
        $rootIdx = $this->unwrapRef($data, 0);

        if (!is_int($rootIdx) || !isset($data[$rootIdx]['data'])) {
            return [];
        }

        $dataIdx = $this->unwrapRef($data, $data[$rootIdx]['data']);

        if (!is_int($dataIdx) || !isset($data[$dataIdx]['planned-works-problems'])) {
            return [];
        }

        $problems = $this->resolve($data, $data[$dataIdx]['planned-works-problems']);

        return is_array($problems) ? ($problems['items'] ?? []) : [];
    }

    private function unwrapRef(array $data, mixed $idx): mixed
    {
        if (!is_int($idx) || !array_key_exists($idx, $data)) {
            return $idx;
        }

        $value = $data[$idx];

        if (is_array($value) && array_is_list($value) && count($value) === 2 &&
            is_string($value[0]) && in_array($value[0], ['ShallowReactive', 'Reactive', 'ShallowRef', 'Ref'], true)) {
            return $value[1];
        }

        return $idx;
    }

    private function resolve(array $data, mixed $idx): mixed
    {
        if (!is_int($idx) || !array_key_exists($idx, $data)) {
            return $idx;
        }

        $value = $data[$this->unwrapRef($data, $idx)];

        if (!is_array($value)) {
            return $value;
        }

        return array_map(fn ($item) => $this->resolve($data, $item), $value);
    }

    /**
     * water.gov.ge exposes only a bare municipality name (e.g. "ხობი"), while
     * ServiceCenter names elsewhere carry the full genitive + "სერვის ცენტრი"
     * form (e.g. "ხობის სერვის ცენტრი"). Match against the existing catalog
     * instead of blindly creating a bare-name duplicate.
     */
    private function resolveServiceCenter(string $name, array $addresses): ServiceCenter
    {
        $name = self::CONTRACTOR_CENTERS[$name] ?? $name;

        // Bakuriani has its own center inside the Borjomi contractor's area.
        if ($name === self::BORJOMI && $addresses && !array_filter($addresses, fn($address) => !str_contains($address, 'ბაკურიან'))) {
            $name = self::BAKURIANI;
        }

        $exact = ServiceCenter::query()->where('name', $name)->first();

        if ($exact) {
            return $exact;
        }

        $stem = mb_substr($name, 0, -1);

        $fuzzy = ServiceCenter::query()
            ->where('name', 'LIKE', $name . '%')
            ->orWhere('name', 'LIKE', $stem . '%')
            ->orderByRaw('LENGTH(name) ASC')
            ->first();

        return $fuzzy ?? ServiceCenter::query()->create(['name' => $name]);
    }

    private function extractAddresses(string $excerpt): array
    {
        if (!preg_match('/მისამართები:\s*(.+)$/u', $excerpt, $matches)) {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $matches[1]))));
    }
}
