@php
    use \Carbon\Carbon;

    Carbon::setLocale('ru');
@endphp

@extends('layout')
@section('title', $title)

@section('content')
    <table class="table-auto w-full text-center">
        <tr>
            @foreach($stat as $name => $datum)
                <td>{{$name}}: {{$datum}}</td>
            @endforeach
        </tr>
    </table>
    <h2 class="text-3xl text-center my-5">
        Актуальные отключения
        ({{ count($currentEvents) }})
    </h2>
    <table class="table-auto w-full text-left overflow-x-scroll">
        @include('table_head', ['withLink' => true])
        <tbody>
            @foreach($currentEvents as $event)
                @include('table_row', ['event' => $event, 'withLink' => true])
            @endforeach
        </tbody>
    </table>

    @if ($graphData)
        @include('chart', ['graphData' => $graphData])
    @endif
@endsection
