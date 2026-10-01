@if ($previous->isNotEmpty())
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between">
            Previous readings
            @can('vitals.view')<a href="{{ route('vitals.index', $patient) }}" class="small fw-normal">Full history</a>@endcan
        </div>
        <ul class="list-group list-group-flush">
            @foreach ($previous as $v)
                <li class="list-group-item">
                    <small class="text-muted">{{ format_date($v->recorded_at, true) }} · {{ $v->recorder?->name }}</small>
                    @include('vitals._summary', ['v' => $v, 'compact' => true])
                </li>
            @endforeach
        </ul>
    </div>
@endif
