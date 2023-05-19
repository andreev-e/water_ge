<p>
    {{ $address->name_ru }} ({{ $address->name }})
    {{ $address->total_events ? '- было ' . $address->total_events . ' отключений' : ''}}
</p>
