<p>
    {{ $address->translit }}
    {{ $address->total_events ? ' (ранее уже было ' . $address->total_events . ' отключений)' : ''}}
</p>
