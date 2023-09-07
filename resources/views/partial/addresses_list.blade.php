<table class="table-auto w-full text-left overflow-x-scroll">
    <thead>
        <tr class="border">
            <th>Адрес</th>
            @if ($withSC)
                <th>Сервис центр</th>
            @endif
            <th>Отключений</th>
        </tr>
    </thead>
    <tbody>
        @foreach($addresses as $address)
            <tr class="border">
                <td>
                    <a
                        class="text-cyan-600"
                        href="{{ route('address', ['address' => $address->id]) }}"
                    >
                        {{ $address->translit  }}
                    </a>
                </td>
                @if ($withSC)
                    <td>
                        <a
                            class="text-cyan-600"
                            href="{{ route('index', ['service_center_id' => $address->serviceCenter->id]) }}"
                        >
                            {{ $address->serviceCenter->name_ru  }}
                        </a>
                    </td>
                @endif
                <td>{{ $address->total_events  }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
