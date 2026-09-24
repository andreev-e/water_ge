@php
    use App\Enums\EventTypes;
    use \Carbon\Carbon;

    Carbon::setLocale('ru');
@endphp

@extends('layout')
@section('title', 'Отключение ' . $event->type->getIcon() . ' в ' . $event->serviceCenter->name_ru . ' ' . mb_strtolower($event->from_to))

@section('content')
    @include('partial.stats', ['stat' => $stat])
    <div class="bg-white rounded-xl border border-slate-200 overflow-x-auto">
        <table class="w-full text-sm text-left">
            @include('table_head', ['withLink' => false])
            <tbody>
                @include('table_row', ['event' => $event, 'withLink' => false])
            </tbody>
        </table>
    </div>
    @include('partial.section_title', ['title' => 'Затронуто'])
    @if ($event->type === EventTypes::gas)
        <div class="bg-white rounded-xl border border-slate-200 px-4 py-3 space-y-2 text-sm">
            @foreach(array_filter([$event->name_ru, $event->name, $event->name_en]) as $text)
                <p>{{ $text }}</p>
            @endforeach
        </div>
    @else
        @include('partial.addresses_list', ['addresses' => $event->addresses, 'withSC' => false])
    @endif
@endsection

