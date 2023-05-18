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
        $currentEvents = Event::getCurrent();
//
//        $addresses = Address::query()
//            ->where('service_center_id', 3)
//            ->withCount('events')
//            ->orderBy('events_count', 'DESC')
//            ->limit(10)
//            ->get();

        $serviceCenters = ServiceCenter::query()
            ->with('addresses')
            ->where('total_events', '>', 10)
            ->orderBy('total_events', 'DESC')
            ->get();

        return view('welcome', compact('currentEvents', 'serviceCenters'));
    }
}
