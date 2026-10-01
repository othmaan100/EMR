@extends('portal.layout')

@section('title', 'Bills & receipts')

@section('content')
<div class="card mb-3">
    <div class="card-body d-flex justify-content-between align-items-center">
        <div>
            <div class="small text-muted">Amount you owe</div>
            <div class="h4 mb-0 {{ $balance > 0 ? 'text-danger' : 'text-success' }}">{{ money($balance) }}</div>
        </div>
        @if ($canPayOnline && $balance > 0)
            <form method="POST" action="{{ route('portal.bills.pay') }}" class="text-end">
                @csrf
                <button class="btn btn-success"><i class="bi bi-credit-card me-1"></i> Pay {{ money($balance) }} online</button>
                <div class="small text-muted mt-1">Card, bank transfer or USSD. Your receipt appears here once paid.</div>
            </form>
        @else
            <span class="small text-muted text-end">Payments are made at the hospital cashier.<br>Always ask for a receipt.</span>
        @endif
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">Bills</div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light"><tr><th class="ps-3">Date</th><th>Visit</th><th class="text-end">Total</th><th class="text-end">You owe</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($bills as $bill)
                            @php $t = $bill->totals(); @endphp
                            <tr>
                                <td class="ps-3 small">{{ format_date($bill->created_at) }}<span class="d-block text-muted">{{ $bill->bill_number }}</span></td>
                                <td class="small">{{ $bill->visit?->clinic?->name ?? ($bill->admission_id ? 'Admission' : 'Registration / other') }}</td>
                                <td class="text-end small">{{ money($t['amount']) }}</td>
                                <td class="text-end fw-semibold {{ $t['balance'] > 0 ? 'text-danger' : 'text-success' }}">{{ money($t['balance']) }}</td>
                                <td class="text-end pe-3"><a href="{{ route('portal.bills.show', $bill) }}" target="_blank" class="btn btn-sm btn-light" title="View bill"><i class="bi bi-eye"></i></a></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted small py-3">No bills.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($bills->hasPages())<div class="card-footer bg-white">{{ $bills->links() }}</div>@endif
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">Receipts</div>
            <ul class="list-group list-group-flush">
                @forelse ($payments as $p)
                    <li class="list-group-item d-flex justify-content-between align-items-center small">
                        <span @class(['text-decoration-line-through text-muted' => $p->isVoided()])>
                            {{ format_date($p->created_at) }} · <strong>{{ money($p->amount) }}</strong>
                            <span class="d-block text-muted">{{ $p->receipt_number }}</span>
                        </span>
                        <a href="{{ route('portal.receipts.show', $p) }}" target="_blank" class="btn btn-sm btn-light">Receipt</a>
                    </li>
                @empty
                    <li class="list-group-item text-muted small">No payments.</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
@endsection
