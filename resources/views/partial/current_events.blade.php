@php
    use \Carbon\Carbon;
@endphp

<div id="live-events" data-live-url="{{ request()->fullUrlWithQuery(['live' => 1]) }}">
    @php
        [$activeEvents, $upcomingEvents] = $currentEvents->partition(fn($event) => $event->start < Carbon::now());
    @endphp
    @include('partial.events_details', ['key' => 'active', 'title' => __('web.active_now'), 'events' => $activeEvents, 'open' => true, 'empty' => __('web.no_active')])
    @include('partial.events_details', ['key' => 'upcoming', 'title' => __('web.upcoming'), 'events' => $upcomingEvents, 'hideEmpty' => true, 'open' => $activeEvents->isEmpty()])
</div>
