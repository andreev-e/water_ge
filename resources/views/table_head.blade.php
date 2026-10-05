<thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
    <tr>
        <th class="px-3 py-2 font-medium whitespace-nowrap">
            {{ __('web.col_what') }}
            @if(request()->has('type'))
                <a class="ml-1 normal-case text-cyan-700 hover:underline" href="{{ request()->fullUrlWithoutQuery('type') }}" title="{{ __('web.reset_filter') }}">✕</a>
            @endif
        </th>
        <th class="px-3 py-2 font-medium whitespace-nowrap">
            {{ __('web.col_where') }}
            @if(request()->route('serviceCenter'))
                <a class="ml-1 normal-case text-cyan-700 hover:underline" href="{{ \App\Support\Locale::route('index', request()->except('live')) }}" title="{{ __('web.reset_filter') }}">✕</a>
            @endif
        </th>
        <th class="px-3 py-2 font-medium whitespace-nowrap">{{ __('web.col_period') }}</th>
        <th class="px-3 py-2 font-medium whitespace-nowrap">{{ __('web.col_addresses_customers') }}</th>
        <th class="px-3 py-2 font-medium whitespace-nowrap">{{ __('web.col_start') }}</th>
        <th class="px-3 py-2 font-medium whitespace-nowrap">{{ __('web.col_finish') }}</th>
    </tr>
</thead>
