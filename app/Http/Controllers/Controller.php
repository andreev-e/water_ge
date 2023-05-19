<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\ServiceCenter;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    public function index()
    {
        $currentEvents = Event::getCurrent();

        $serviceCenters = ServiceCenter::query()
            ->limit(5)
            ->orderBy('total_events', 'DESC')
            ->get();

        $events = Event::query()
            ->with('serviceCenter')
            ->where('start', '>=', now()->subDays(90))
            ->orderBy('start')
            ->get();

        $graphData = [];
        $graphData['labels'] = [];
        $graphData['datasets'] = [];

        foreach ($serviceCenters as $serviceCenter) {
            $color = $this->rand_color();
            $graphData['datasets'][$serviceCenter->id]['label'] = $serviceCenter->name_ru;
            $graphData['datasets'][$serviceCenter->id]['backgroundColor'] = $color;
            $graphData['datasets'][$serviceCenter->id]['borderColor'] = $color;
            $graphData['datasets'][$serviceCenter->id]['fill'] = false;
        }

        /** @var Event $event */
        foreach ($events as $event) {
            $graphData['labels'][] = $event->start->format('d.m.Y');

            foreach ($serviceCenters as $serviceCenter) {
                $graphData['datasets'][$serviceCenter->id]['data'][] = $serviceCenter->id === $event->serviceCenter->id ? $event->addresses()->count() : 0;
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
