<?php

namespace App\Http\Controllers;

use App\Models\Address;
use App\Models\Event;
use App\Models\ServiceCenter;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    public function index()
    {
        $currentEvents = Event::query()
            ->where('finish', '>=', Carbon::now())
            ->get();
//
//        $addresses = Address::query()
//            ->where('service_center_id', 3)
//            ->withCount('events')
//            ->orderBy('events_count', 'DESC')
//            ->limit(10)
//            ->get();

        $serviceCenters = ServiceCenter::query()
            ->where('total_events', '>', 10)
            ->orderBy('total_events', 'DESC')
            ->get();

        $addresses = Address::query()
            ->with('serviceCenter')
            ->orderBy('total_events', 'DESC')
            ->limit(100)
            ->get();

        return view('welcome', compact('currentEvents', 'serviceCenters', 'addresses'));
    }
}
