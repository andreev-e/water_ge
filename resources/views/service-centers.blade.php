@php
    use \Carbon\Carbon;

    Carbon::setLocale('ru');
@endphp

@extends('layout')
@section('title', 'Сервис центры')

@section('content')
    @include('partial.stats', ['stat' => $stat])
    <table class="table-auto w-full text-left overflow-x-scroll">
        <thead>
            <tr class="border">
                <th>Сервис центр</th>
                <th>Отключений</th>
                <th>Подписчиков</th>
            </tr>
        </thead>
        <tbody>
            @foreach($serviceCenters as $serviceCenter)
                <tr class="border">
                    <td>
                        <a class="text-cyan-600" href="{{ route('index', ['service_center_id' => $serviceCenter->id]) }}">
                            {{ $serviceCenter->name_ru  }}
                        </a>
                    </td>
                    <td>{{ $serviceCenter->total_events  }}</td>
                    <td>{{ $serviceCenter->subscriptions_count  }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection

