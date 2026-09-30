@php
    use \Carbon\Carbon;

    // Also rendered on its own for live refresh, outside index.blade.php.
    Carbon::setLocale('ru');
@endphp

<div id="live-events" data-live-url="{{ request()->fullUrlWithQuery(['live' => 1]) }}">
    @if (request()->has('service_center_id'))
        <section data-live-section="all">
            @include('partial.section_title', ['title' => 'Актуальные отключения', 'count' => count($currentEvents)])
            @include('partial.events_list', ['events' => $currentEvents])
        </section>
    @else
        @php
            [$activeEvents, $upcomingEvents] = $currentEvents->partition(fn($event) => $event->start < Carbon::now());
        @endphp
        @include('partial.events_details', ['key' => 'active', 'title' => 'Идут сейчас', 'events' => $activeEvents, 'open' => true, 'empty' => 'Сейчас отключений нет.'])
        @include('partial.events_details', ['key' => 'upcoming', 'title' => 'Будущие отключения', 'events' => $upcomingEvents, 'hideEmpty' => true])
    @endif
</div>
