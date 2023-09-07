@php
    use App\Enums\EventTypes;
    use \Carbon\Carbon;

    Carbon::setLocale('ru');
@endphp

@extends('layout')
@section('title', 'Отключение ' . $event->type->getIcon() . ' в ' . $event->serviceCenter->name_ru . ' ' . mb_strtolower($event->from_to))

@section('content')
    @include('partial.stats', ['stat' => $stat])
    <table class="table-auto w-full text-left overflow-x-scroll">
        @include('table_head', ['withLink' => false])
        <tbody>
            @include('table_row', ['event' => $event, 'withLink' => false])
        </tbody>
    </table>
    <h2 class="text-3xl text-center my-5">
        Затронуто
    </h2>
    @if ($event->type === EventTypes::gas)
        <p>{{$event->name_ru}}</p>
        <p>{{$event->name}}</p>
        <p>{{$event->nam_en}}</p>
    @else
        @include('partial.addresses_list', ['addresses' => $event->addresses, 'withSC' => false])
    @endif
@endsection

