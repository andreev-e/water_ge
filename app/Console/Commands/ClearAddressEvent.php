<?php

namespace App\Console\Commands;

use App\Models\Address;
use App\Models\Event;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ClearAddressEvent extends Command
{
    protected $signature = 'app:clear-address-event';

    protected $description = 'Command description';

    public function handle()
    {
        $addressIds = Address::query()
            ->select('id')
            ->get()
            ->pluck('id')
            ->toArray();

        DB::query()
            ->from('address_event')
            ->whereNotIn('address_id', $addressIds)
            ->delete();

        $eventIds = Event::query()
            ->select('id')
            ->get()
            ->pluck('id')
            ->toArray();

        DB::query()
            ->from('address_event')
            ->whereNotIn('event_id', $eventIds)
            ->delete();
    }
}
