<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Отключения воды в Грузии</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body>
<div
    class="p-10 w-full">
    <table class="table-auto w-full text-center">
        <tr>
            <td>
                Сервисных центров: <?= \App\Models\ServiceCenter::query()->count() ?>
            </td>
            <td>Адресов в базе: <?= \App\Models\Address::query()->count() ?></td>
            <td>Событий в базе: <?= \App\Models\Event::query()->count() ?></td>
        </tr>
        <tr>
            <td colspan="3">

            </td>
        </tr>
    </table>
    <h2 class="text-3xl text-center">Актуальные отключения</h2>
    <table class="table-auto w-full text-center">
        <thead>
            <tr>
                <th>Город</th>
                <th>Период</th>
                <th>Адреса</th>
            </tr>
        </thead>
        <tbody>
            @foreach($currentEvents as $event)
                <tr>
                    <td>{{ $event->serviceCenter->name_ru }} ({{ $event->serviceCenter->name }})</td>
                    <td>{{ $event->start }} - {{ $event->finish }}</td>
                    <td class="text-left">
                        @foreach($event->addresses as $address)
                            <p>{{ $address->name_ru }} ({{ $address->name }})</p>
                        @endforeach
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <h2 class="text-3xl text-center">Статистика</h2>
    @foreach($addresses as $addresse)
        <tr>
            <td>{{ $address->name_ru }} ({{ $address->name }})</td>
            <td>{{ $address->events_count }}</td>
        </tr>
    @endforeach
</div>
</body>
</html>
