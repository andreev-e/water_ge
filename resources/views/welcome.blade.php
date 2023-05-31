@php
    use \Carbon\Carbon;

    Carbon::setLocale('ru');
@endphp

@extends('layout')
@section('title', 'Отключения воды, электричества и газа в Грузии')

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
    <h2 class="text-3xl text-center my-5">Статистика (только вода и электроэнергия)</h2>
    <canvas id="eventsChart"></canvas>
    <script>
        const ctx = document.getElementById('eventsChart');
        const labels = {!! json_encode($graphData['labels']) !!};
        const datasets = {!! json_encode($graphData['datasets']) !!};

        const data = {
            labels,
            datasets,
        };

        new Chart(ctx, {
            type: 'line',
            data: data,
            options: {
                plugins: {
                    title: {
                        text: 'Отключения по городам',
                        display: true,
                    },
                },
                scales: {
                    x: {
                        title: {
                            display: true,
                            text: 'Даты',
                        },
                    },
                    y: {
                        title: {
                            display: true,
                            text: '% адресов за вычетом отключенных',
                        },
                    },
                },
            },
        });
    </script>
@endsection
