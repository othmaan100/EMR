@extends('layouts.app')

@section('title', 'Pre-authorisation (PA) codes')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <ul class="nav nav-pills">
        @foreach (\App\Models\Preauthorization::STATUSES as $key => $s)
            <li class="nav-item">
                <a href="{{ route('preauth.index', ['status' => $key]) }}" @class(['nav-link', 'active' => $status === $key])>
                    {{ $s['label'] }} @if ($counts[$key] ?? 0)<span class="badge rounded-pill text-bg-light border ms-1">{{ $counts[$key] }}</span>@endif
                </a>
            </li>
        @endforeach
    </ul>
    <span class="small text-muted">New requests are made from the patient's billing account.</span>
</div>

@error('code')<div class="alert alert-danger">{{ $message }}</div>@enderror
@error('status')<div class="alert alert-danger">{{ $message }}</div>@enderror

<div class="card">
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
                <tr><th class="ps-3">Requested</th><th>Patient</th><th>HMO</th><th>Services</th><th class="text-end">Est. cost</th>
                    <th>{{ $status === 'requested' ? 'Record HMO reply' : 'Outcome' }}</th></tr>
            </thead>
            <tbody>
                @forelse ($requests as $pa)
                    <tr>
                        <td class="ps-3 small text-nowrap">{{ format_date($pa->created_at, true) }}<span class="d-block text-muted">{{ $pa->requester?->name }}</span></td>
                        <td>
                            @can('billing.view')
                                <a href="{{ route('billing.account', $pa->patient) }}">{{ $pa->patient->list_name }}</a>
                            @else
                                {{ $pa->patient->list_name }}
                            @endcan
                            <small class="d-block text-muted">{{ $pa->patient->hospital_number }} · {{ $pa->patient->insurance_number }}</small>
                        </td>
                        <td class="small">{{ $pa->insuranceProvider->name }}</td>
                        <td class="small" style="max-width: 22rem;">
                            {{ $pa->services }}
                            @if ($pa->diagnosis)<span class="d-block text-muted">Dx: {{ $pa->diagnosis }}</span>@endif
                            @if ($pa->bill)<span class="d-block text-muted">Bill {{ $pa->bill->bill_number }}</span>@endif
                        </td>
                        <td class="text-end small">{{ $pa->amount_requested !== null ? money($pa->amount_requested) : '—' }}</td>
                        <td style="min-width: 20rem;">
                            @if ($pa->status === 'requested')
                                <form method="POST" action="{{ route('preauth.decide', $pa) }}" class="row g-1">
                                    @csrf
                                    <div class="col-6"><input type="text" name="code" maxlength="50" class="form-control form-control-sm" placeholder="PA code" aria-label="PA code"></div>
                                    <div class="col-6"><input type="number" name="amount_approved" min="0" step="0.01" class="form-control form-control-sm" placeholder="Approved amount" aria-label="Approved amount"></div>
                                    <div class="col-6"><input type="date" name="valid_until" class="form-control form-control-sm" aria-label="Valid until" title="Valid until"></div>
                                    <div class="col-6"><input type="text" name="notes" maxlength="500" class="form-control form-control-sm" placeholder="Note" aria-label="Note"></div>
                                    <div class="col-12 d-flex gap-1">
                                        <button name="status" value="approved" class="btn btn-sm btn-success flex-fill">Approved</button>
                                        <button name="status" value="declined" class="btn btn-sm btn-outline-danger flex-fill">Declined</button>
                                    </div>
                                </form>
                            @else
                                <div class="small">
                                    @if ($pa->code)<strong>{{ $pa->code }}</strong>@endif
                                    @if ($pa->amount_approved !== null) · {{ money($pa->amount_approved) }}@endif
                                    @if ($pa->valid_until) · valid to {{ format_date($pa->valid_until) }}
                                        @if ($pa->isExpired())<span class="badge text-bg-secondary">expired</span>@endif
                                    @endif
                                    <span class="d-block text-muted">{{ $pa->decider?->name }}, {{ format_date($pa->decided_at, true) }}</span>
                                    @if ($pa->notes)<span class="d-block">{{ $pa->notes }}</span>@endif
                                </div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">Nothing here.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($requests->hasPages())<div class="card-footer bg-white">{{ $requests->links() }}</div>@endif
</div>
@endsection
