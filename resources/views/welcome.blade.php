<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Отключения воды в Грузии</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body>
<div
    class="px-10 w-full">
    <table class="table-auto w-full text-center">
        <tr>
            <td>
                Сервисных центров: <?= \App\Models\ServiceCenter::query()->count() ?>
            </td>
            <td>Адресов в базе: <?= \App\Models\Address::query()->count() ?></td>
            <td>Событий в базе: <?= \App\Models\Event::query()->count() ?></td>
        </tr>
    </table>
    <h2 class="text-3xl text-center">Актуальные отключения</h2>
    <table class="table-auto w-full text-center">
        <thead>
            <tr class="border">
                <th class="align-top">Город</th>
                <th class="align-top">Период</th>
                <th>Адреса</th>
            </tr>
        </thead>
        <tbody>
            @foreach($currentEvents as $event)
                <tr class="border" x-data="{ open: false }">
                    <td>{{ $event->serviceCenter->name_ru }}
                        ({{ $event->serviceCenter->name }})
                        <b>{{ round($event->addresses->count() / $event->serviceCenter->total_addresses * 100) }}%
                            адресов</b>
                    </td>
                    <td>{{ $event->start->format('d.m.Y H:i') }} - {{ $event->finish->format('d.m.Y H:i') }}</td>
                    <td>
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
                                <p>{{ $address->name_ru }} ({{ $address->name }})</p>
                            @endforeach
                        </div>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <h2 class="text-3xl text-center">Статистика</h2>
    <h2 class="text-2xl text-center">По сервисным центрам</h2>
    <table class="table-auto w-full text-left">
        <thead>
            <tr>
                <th>Город</th>
                <th>Число отключений</th>
            </tr>
        </thead>
        @foreach($serviceCenters as $serviceCenter)
            <tr class="border">
                <td>{{ $serviceCenter->name_ru }} ({{ $serviceCenter->name }})</td>
                <td>{{ $serviceCenter->total_events }}</td>
            </tr>
        @endforeach
    </table>
    <h2 class="text-2xl text-center">По адресам</h2>
    <table class="table-auto w-full text-left">
        <thead>
            <tr>
                <th>Адрес</th>
                <th>Число отключений</th>
            </tr>
        </thead>
        @foreach($addresses as $address)
            <tr class="border">
                <td>{{ $address->name_ru }} ({{ $address->name }}) - {{ $address->serviceCenter->name_ru }}</td>
                <td>{{ $address->total_events }}</td>
            </tr>
        @endforeach
    </table>
</div>
</body>
</html>
