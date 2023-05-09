<?php

namespace App\Console\Commands;

use App\Models\Address;
use App\Models\ServiceCenter;
use Illuminate\Console\Command;

class CountStats extends Command
{
    protected $signature = 'app:count-stats';

    protected $description = 'Command description';

    public function handle()
    {
//        ServiceCenter::query()->each(function(ServiceCenter $serviceCenter) {
//            $serviceCenter->total_addresses = $serviceCenter->addresses()->count();
//            $serviceCenter->total_events = $serviceCenter->events()->count();
//            $serviceCenter->save();
//        });

        Address::query()->each(function(Address $address) {
            $address->total_events = $address->events()->count();
            $address->save();
        });
    }
}
