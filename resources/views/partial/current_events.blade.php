{{-- The wrapper depends on the request URL, the sections inside are cached HTML (Controller::currentEventsHtml). --}}
<div id="live-events" data-live-url="{{ request()->fullUrlWithQuery(['live' => 1]) }}">
    {!! $eventsHtml !!}
</div>
