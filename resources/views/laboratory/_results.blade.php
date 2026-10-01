{{--
    Read-only results table for one lab order.
    Abnormal values carry an icon and a letter as well as colour.
    @param LabOrder $order
--}}
<table class="table table-sm align-middle mb-0">
    <thead class="table-light">
        <tr><th class="ps-2">Test / parameter</th><th>Result</th><th>Unit</th><th>Reference</th><th>Flag</th></tr>
    </thead>
    <tbody>
        @foreach ($order->items->where('status', '!=', 'cancelled') as $item)
            <tr class="table-group-divider">
                <td colspan="5" class="ps-2 fw-semibold bg-light">
                    {{ $item->test->name }}
                    @unless (in_array($item->status, ['resulted', 'verified']))<span class="badge text-bg-secondary ms-1">Pending</span>@endunless
                </td>
            </tr>
            @foreach ($item->results as $r)
                <tr>
                    <td class="ps-4">{{ $r->name }}</td>
                    <td @class([\App\Models\LabResult::FLAGS[$r->flag]['class'] ?? ''])>{{ $r->value }}</td>
                    <td class="small text-muted">{{ $r->unit }}</td>
                    <td class="small text-muted">{{ $r->reference }}</td>
                    <td>
                        @if ($r->flag)
                            @php $f = \App\Models\LabResult::FLAGS[$r->flag]; @endphp
                            <span class="{{ $f['class'] }}"><i class="bi {{ $f['icon'] }}"></i> {{ $f['short'] }}</span>
                        @endif
                    </td>
                </tr>
            @endforeach
            @if ($item->comment)
                <tr><td colspan="5" class="ps-4 small"><i class="bi bi-chat-left-text me-1"></i>{{ $item->comment }}</td></tr>
            @endif
        @endforeach
    </tbody>
</table>
