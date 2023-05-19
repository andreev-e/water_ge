<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\ServiceCenter;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Cache;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    public function index()
    {
        $currentEvents = Event::getCurrent();

        $serviceCenters =  Cache::remember('serviceCenters', 60 * 60, static function () {
            return ServiceCenter::query()
                ->with('addresses')
                ->orderBy('total_events', 'DESC')
                ->get();
        });

        $events = Event::query()
            ->with('serviceCenter', 'addresses')
            ->where('start', '>=', now()->subDays(120))
            ->orderBy('start')
            ->get();

        $graphData = [];
        $graphData['labels'] = [];
        $graphData['datasets'] = [];

        foreach ($events as $event) {
            $date = $event->start->format('d.m.Y');
            if (!in_array($date, $graphData['labels'], true)) {
                $graphData['labels'][] = $date;
            }
        }

        $serviceCentersToDraw = 10;
        foreach ($serviceCenters->slice(0, $serviceCentersToDraw) as $serviceCenter) {
            $color = $this->rand_color();
            $graphData['datasets'][$serviceCenter->id]['label'] = $serviceCenter->name_ru;
            $graphData['datasets'][$serviceCenter->id]['backgroundColor'] = $color;
            $graphData['datasets'][$serviceCenter->id]['borderColor'] = $color;
            $graphData['datasets'][$serviceCenter->id]['fill'] = false;
        }

        foreach ($graphData['labels'] as $date) {
            foreach ($serviceCenters->slice(0, $serviceCentersToDraw) as $serviceCenter) {
                $found = false;
                foreach ($events as $event) {
                    if ($date === $event->start->format('d.m.Y') && $serviceCenter->id === $event->serviceCenter->id) {
                        $found = true;
                        $number = $serviceCenter->total_addresses - $event->total_addresses;
                        $graphData['datasets'][$serviceCenter->id]['data'][] = $number;
                        break;
                    }
                }
                if (!$found) {
                    $graphData['datasets'][$serviceCenter->id]['data'][] = $serviceCenter->total_addresses;
                }
            }
        }

        $graphData['datasets'] = array_values($graphData['datasets']);

        return view('welcome', compact('currentEvents', 'serviceCenters', 'graphData'));
    }

    function rand_color()
    {
        return '#' . str_pad(dechex(mt_rand(0, 0xFFFFFF)), 6, '0', STR_PAD_LEFT);
    }
}
