@php
    use App\Enums\EventTypes;
@endphp

@extends('layout')
@section('title', __('web.title_event', ['icon' => $event->type->getIcon(), 'center' => $event->serviceCenter->localizedName(app()->getLocale()), 'period' => mb_strtolower($event->fromToIn(app()->getLocale()))]))

@section('content')
    @include('partial.stats', ['stat' => $stat])
    <div class="bg-white rounded-xl border border-slate-200 overflow-x-auto overflow-y-hidden">
        <table class="w-full text-sm text-left">
            @include('table_head', ['withLink' => false])
            <tbody>
                @include('table_row', ['event' => $event, 'withLink' => false])
            </tbody>
        </table>
    </div>
    @include('partial.section_title', ['title' => __('web.affected')])
    @if ($event->type === EventTypes::gas)
        <div class="bg-white rounded-xl border border-slate-200 px-4 py-3 space-y-2 text-sm">
            {{-- The visitor's language first, the other versions after it. --}}
            @foreach(array_unique(array_filter([$event->localizedName(app()->getLocale()), $event->name_ru, $event->name, $event->name_en])) as $text)
                <p>{{ $text }}</p>
            @endforeach
        </div>
    @else
        @include('partial.addresses_list', ['addresses' => $event->addresses, 'withSC' => false])
    @endif
@endsection

