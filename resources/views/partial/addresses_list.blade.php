<div class="bg-white rounded-xl border border-slate-200 overflow-x-auto">
    <table class="w-full text-sm text-left">
        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
            <tr>
                <th class="px-3 py-2 font-medium">Адрес</th>
                @if ($withSC)
                    <th class="px-3 py-2 font-medium">Сервис центр</th>
                @endif
                <th class="px-3 py-2 font-medium text-right">Отключений</th>
            </tr>
        </thead>
        <tbody>
            @foreach($addresses as $address)
                <tr class="border-t border-slate-100 hover:bg-slate-50">
                    <td class="px-3 py-2">
                        <a class="text-cyan-700 hover:underline" href="{{ route('address', ['address' => $address->id]) }}">
                            {{ $address->translit }}
                        </a>
                    </td>
                    @if ($withSC)
                        <td class="px-3 py-2">
                            <a class="text-cyan-700 hover:underline" href="{{ route('index', ['service_center_id' => $address->serviceCenter->id]) }}">
                                {{ $address->serviceCenter->name_ru }}
                            </a>
                        </td>
                    @endif
                    <td class="px-3 py-2 text-right tabular-nums">{{ $address->total_events }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
