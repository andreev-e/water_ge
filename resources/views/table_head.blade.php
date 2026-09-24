<thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
    <tr>
        <th class="px-3 py-2 font-medium whitespace-nowrap">
            Что
            @if(request()->has('type'))
                <a class="ml-1 normal-case text-cyan-700 hover:underline" href="{{ request()->fullUrlWithoutQuery('type') }}" title="Сбросить фильтр">✕</a>
            @endif
        </th>
        <th class="px-3 py-2 font-medium whitespace-nowrap">
            Где
            @if(request()->has('service_center_id'))
                <a class="ml-1 normal-case text-cyan-700 hover:underline" href="{{ request()->fullUrlWithoutQuery('service_center_id') }}" title="Сбросить фильтр">✕</a>
            @endif
        </th>
        <th class="px-3 py-2 font-medium whitespace-nowrap">Адресов / потребителей</th>
        <th class="px-3 py-2 font-medium whitespace-nowrap">Отключение</th>
        <th class="px-3 py-2 font-medium whitespace-nowrap">Включат</th>
        <th class="px-3 py-2 font-medium whitespace-nowrap">Период</th>
    </tr>
</thead>
