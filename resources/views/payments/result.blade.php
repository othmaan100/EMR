@extends('layouts.guest')

@section('title', 'Payment')

@section('content')
<div class="container d-flex align-items-center justify-content-center min-vh-100 py-4">
    <div class="card w-100 text-center" style="max-width: 440px;">
        <div class="card-body p-4">
            @if (setting('logo'))<img src="{{ asset(setting('logo')) }}" alt="" style="max-height: 64px;" class="mb-2">@endif
            <h1 class="h5">{{ setting('hospital_name') }}</h1>
            @if ($payment->status === 'success')
                <div class="display-6 text-success my-2"><i class="bi bi-check-circle"></i></div>
                <p class="mb-1">Thank you. Your payment of <strong>{{ money($payment->amount) }}</strong> was received.</p>
                <p class="small text-muted mb-0">Reference {{ $payment->reference }}. Your receipt is available at the cashier{{ setting('portal_enabled') ? ' and in the patient portal' : '' }}.</p>
            @else
                <div class="display-6 text-warning my-2"><i class="bi bi-exclamation-circle"></i></div>
                <p class="mb-1">This payment was not completed{{ $payment->created_at->lt(now()->subDays(7)) ? ' and the link has expired' : '' }}.</p>
                <p class="small text-muted mb-0">No money was taken for reference {{ $payment->reference }}. Please ask the cashier for a new link, or pay at the hospital.</p>
            @endif
        </div>
    </div>
</div>
@endsection
