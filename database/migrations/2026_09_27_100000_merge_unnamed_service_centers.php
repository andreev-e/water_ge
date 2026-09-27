<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * LoadWater didn't recognize two water.gov.ge contractors and created
 * nameless duplicates of existing centers: "გარდაბანი" for Gardabani, and
 * "ბორჯომი დ. ბაკურიანი" covering the Borjomi municipality, whose Bakuriani
 * part has its own center. Their events and addresses move to those centers.
 */
return new class extends Migration
{
    private const BAKURIANI_MARK = 'ბაკურიან';

    public function up(): void
    {
        $gardabani = $this->centerId('გარდაბნის სერვის ცენტრი');
        $borjomi = $this->centerId('ბორჯომის სერვის ცენტრი');
        $bakuriani = $this->centerId('ბაკურიანის სერვის ცენტრი');

        if ($source = $this->centerId('გარდაბანი')) {
            $this->merge($source, fn() => $gardabani, fn() => $gardabani);
        }

        if ($source = $this->centerId('ბორჯომი დ. ბაკურიანი')) {
            $this->merge(
                $source,
                fn(string $address) => str_contains($address, self::BAKURIANI_MARK) ? $bakuriani : $borjomi,
                fn(array $addresses) => $addresses && !array_filter($addresses, fn($address) => !str_contains($address, self::BAKURIANI_MARK))
                    ? $bakuriani
                    : $borjomi,
            );
        }
    }

    public function down(): void
    {
        // The merged centers can't be told apart again.
    }

    private function centerId(string $name): ?int
    {
        return DB::table('service_centers')->where('name', $name)->value('id');
    }

    /**
     * @param callable(string): int $addressTarget center for an address name
     * @param callable(string[]): int $eventTarget center for an event by its address names
     */
    private function merge(int $source, callable $addressTarget, callable $eventTarget): void
    {
        if (!$eventTarget([])) {
            return;
        }

        DB::transaction(function () use ($source, $addressTarget, $eventTarget) {
            $events = DB::table('events')->where('service_center_id', $source)->pluck('id');
            foreach ($events as $eventId) {
                $names = DB::table('address_event')
                    ->join('addresses', 'addresses.id', '=', 'address_event.address_id')
                    ->where('address_event.event_id', $eventId)
                    ->pluck('addresses.name')
                    ->all();
                DB::table('events')->where('id', $eventId)->update(['service_center_id' => $eventTarget($names)]);
            }

            foreach (DB::table('addresses')->where('service_center_id', $source)->get() as $address) {
                $target = $addressTarget($address->name);
                $twin = DB::table('addresses')
                    ->where('service_center_id', $target)
                    ->where('name', $address->name)
                    ->value('id');

                if (!$twin) {
                    DB::table('addresses')->where('id', $address->id)->update(['service_center_id' => $target]);
                    continue;
                }

                $eventIds = DB::table('address_event')->where('address_id', $address->id)->pluck('event_id');
                DB::table('address_event')->insertOrIgnore(
                    $eventIds->map(fn($eventId) => ['address_id' => $twin, 'event_id' => $eventId])->all()
                );
                DB::table('address_event')->where('address_id', $address->id)->delete();
                DB::table('addresses')->where('id', $address->id)->delete();
            }

            DB::table('subscriptions')->where('service_center_id', $source)->update(['service_center_id' => $eventTarget([])]);
            DB::table('service_centers')->where('id', $source)->delete();
        });
    }
};
