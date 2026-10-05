<div class="bg-white rounded-xl border border-slate-200 overflow-x-auto">
    <table class="w-full text-sm text-left">
        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
            <tr>
                <th class="px-3 py-2 font-medium">{{ __('web.col_address') }}</th>
                @if ($withSC)
                    <th class="px-3 py-2 font-medium">{{ __('web.col_service_center') }}</th>
                @endif
                <th class="px-3 py-2 font-medium text-right">{{ __('web.col_outages') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($addresses as $address)
                <tr class="border-t border-slate-100 hover:bg-slate-50">
                    <td class="px-3 py-2">
                        <a class="text-cyan-700 hover:underline" href="{{ \App\Support\Locale::route('address', ['address' => $address->id]) }}">
                            {{ $address->localizedName(app()->getLocale()) }}
                        </a>
                    </td>
                    @if ($withSC)
                        <td class="px-3 py-2">
                            <a class="text-cyan-700 hover:underline" href="{{ \App\Support\Locale::route('service-center', ['serviceCenter' => $address->serviceCenter]) }}">
                                {{ $address->serviceCenter->localizedName(app()->getLocale()) }}
                            </a>
                        </td>
                    @endif
                    <td class="px-3 py-2 text-right tabular-nums">{{ $address->total_events }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
