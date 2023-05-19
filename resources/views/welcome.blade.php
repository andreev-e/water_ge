<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Отключения воды в Грузии</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<div
    class="px-10 py-6 w-full">
    <table class="table-auto w-full text-center">
        <tr>
            <td>
                Сервисных центров: <?= \App\Models\ServiceCenter::query()->count() ?>
            </td>
            <td>Адресов в базе: <?= \App\Models\Address::query()->count() ?></td>
            <td>Событий в базе: <?= \App\Models\Event::query()->count() ?></td>
        </tr>
    </table>
    <h2 class="text-3xl text-center my-5">Актуальные отключения</h2>
    <table class="table-auto w-full text-left">
        <thead>
            <tr class="border">
                <th class="align-top">Город</th>
                <th class="align-top">Период</th>
                <th>Отключенные адреса</th>
            </tr>
        </thead>
        <tbody>
            @foreach($currentEvents as $event)
                <tr class="border text-left">
                    <td class="p-1"><b>{{ $event->serviceCenter->name_ru }}</b>
                        ({{ $event->serviceCenter->name }})
                        <b>~{{ round($event->total_addresses / $event->serviceCenter->total_addresses * 100) }}%
                            адресов</b>
                        ({{ $event->total_addresses }} адрес)
                    </td>
                    <td>{{ $event->start->format('d.m.Y H:i') }} - {{ $event->finish->format('d.m.Y H:i') }}</td>
                    <td x-data="{ open: false }">
                        <button
                            class="btn bg-slate-200 p-2"
                            x-on:click="open = ! open"
                        >
                            Показать
                        </button>
                        <div
                            x-show="open"
                            @click.outside="open = false"
                            class="absolute bg-white shadow-2xl p-8 border-1 right-1 text-left"
                        >
                            @foreach($event->addresses as $address)
                                @include('address', ['address' => $address])
                            @endforeach
                        </div>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <h2 class="text-3xl text-center my-5">Статистика</h2>
    <canvas id="eventsChart"></canvas>
    <script>
        const ctx = document.getElementById('eventsChart');
        const labels = {!! json_encode($graphData['labels']) !!};
        const datasets = {!! json_encode($graphData['datasets']) !!};

        const data = {
            labels,
            datasets
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
                            text: 'Число адресов за вычетом отключенных',
                        },
                    },
                },
            },
        });
    </script>
    <h3 class="text-2xl text-center">По сервисным центрам</h3>
    <table class="table-auto w-full text-left" x-data="{ showAll: false }">
        <thead>
            <tr>
                <th></th>
                <th>Город</th>
                <th>Число отключений</th>
                <th>Часто отключаемые адреса</th>
            </tr>
        </thead>
        @php $i = 0; @endphp
        @foreach($serviceCenters as $serviceCenter)
            <tr
                @if($i > 9)
                    x-show="showAll"
                @endif
                class="border"
            >
                <td>{{++$i}}</td>
                <td>{{ $serviceCenter->name_ru }} ({{ $serviceCenter->name }})</td>
                <td>{{ $serviceCenter->total_events }}</td>
                <td x-data="{ open: false }">
                    <button
                        class="btn bg-slate-200 p-2"
                        x-on:click="open = ! open"
                    >
                        Показать
                    </button>
                    <div
                        x-show="open"
                        @click.outside="open = false"
                        class="absolute bg-white shadow-2xl p-8 border-1 right-1 text-left"
                    >
                        @foreach($serviceCenter->addresses->slice(0, 50) as $address)
                            @include('address', ['address' => $address])
                        @endforeach
                    </div>
                </td>
            </tr>
        @endforeach
        <tr>
            <td colspan="4" class="text-center">
                <button
                    class="btn bg-slate-200 p-2 w-full"
                    x-on:click="showAll = ! showAll"
                >
                    Показать все
                </button>
            </td>
        </tr>
    </table>
</div>
</body>
</html>
