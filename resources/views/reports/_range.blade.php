{{-- Date-range picker with quick presets. --}}
@php
    $presets = [
        'Today' => [today(), today()],
        'Last 7 days' => [today()->subDays(6), today()],
        'This month' => [today()->startOfMonth(), today()],
        'Last month' => [today()->subMonthNoOverflow()->startOfMonth(), today()->subMonthNoOverflow()->endOfMonth()],
        'This year' => [today()->startOfYear(), today()],
    ];
    $extra = array_filter($extra ?? []);
@endphp
<form method="GET" class="d-flex flex-wrap gap-2 align-items-end no-print">
    @foreach ($extra as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
    <div>
        <label for="from" class="form-label small mb-1">From</label>
        <input type="date" id="from" name="from" value="{{ $from->toDateString() }}" class="form-control form-control-sm">
    </div>
    <div>
        <label for="to" class="form-label small mb-1">To</label>
        <input type="date" id="to" name="to" value="{{ $to->toDateString() }}" class="form-control form-control-sm">
    </div>
    {{ $slot ?? '' }}
    <button class="btn btn-sm btn-primary">Apply</button>
    <div class="btn-group btn-group-sm flex-wrap" role="group" aria-label="Presets">
        @foreach ($presets as $label => [$pf, $pt])
            <a href="{{ request()->fullUrlWithQuery(['from' => $pf->toDateString(), 'to' => $pt->toDateString(), 'export' => null]) }}"
               @class(['btn', 'btn-secondary' => $from->isSameDay($pf) && $to->isSameDay($pt), 'btn-outline-secondary' => ! ($from->isSameDay($pf) && $to->isSameDay($pt))])>{{ $label }}</a>
        @endforeach
    </div>
</form>
