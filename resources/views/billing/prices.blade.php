@extends('layouts.app')

@section('title', 'Price List')

@section('content')
<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <ul class="nav nav-pills gap-1">
        @foreach (\App\Http\Controllers\PriceListController::TYPES as $key => $t)
            <li class="nav-item">
                <a @class(['nav-link py-1', 'active' => $type === $key]) href="{{ route('billing.prices', ['type' => $key, 'provider_id' => $provider?->id]) }}">
                    {{ $t['label'] }}
                    @if ($unpriced[$key])<span class="badge text-bg-warning ms-1" title="Items with no default price are not charged">{{ $unpriced[$key] }} unpriced</span>@endif
                </a>
            </li>
        @endforeach
    </ul>
    <form method="GET" class="d-flex gap-2">
        <input type="hidden" name="type" value="{{ $type }}">
        <select name="provider_id" class="form-select" onchange="this.form.submit()" aria-label="Price list">
            <option value="">Default price (self-pay)</option>
            @foreach ($providers as $id => $name)<option value="{{ $id }}" @selected($provider?->id === $id)>{{ $name }} prices</option>@endforeach
        </select>
        <input type="search" name="q" value="{{ $q }}" class="form-control" placeholder="Search…" aria-label="Search">
    </form>
</div>

<div class="alert alert-info small">
    @if ($provider)
        Prices charged to <strong>{{ $provider->name }}</strong> patients (covers {{ $provider->coverage_percent }}%). Leave blank to use the default price.
    @else
        Default prices for self-pay patients, also used for insured patients when no insurer-specific price is set.
        <strong>Items without a default price are not charged.</strong>
    @endif
</div>

<form method="POST" action="{{ route('billing.prices.update') }}">
    @csrf
    <input type="hidden" name="type" value="{{ $type }}">
    <input type="hidden" name="provider_id" value="{{ $provider?->id }}">
    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Item</th>
                        @if ($def['group'])<th>{{ ucfirst($def['group']) }}</th>@endif
                        @if ($provider)<th class="text-end">Default</th>@endif
                        <th style="width: 12rem;">{{ $provider ? $provider->name.' price' : 'Price' }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $item)
                        @php
                            $default = $item->prices->firstWhere('insurance_provider_id', null)?->amount;
                            $own = $provider ? $item->prices->firstWhere('insurance_provider_id', $provider->id)?->amount : $default;
                        @endphp
                        <tr>
                            <td class="ps-3">{{ $item->billingLabel() }}
                                @if ($type === 'services' && $item->clinic_id)<span class="badge text-bg-light border">clinic fee</span>@endif
                                @if ($type === 'drugs')<small class="text-muted">per {{ $item->unit }}</small>@endif
                            </td>
                            @if ($def['group'])<td class="small text-muted">{{ $item->{$def['group']} }}</td>@endif
                            @if ($provider)<td class="text-end small text-muted">{{ $default !== null ? money($default) : '—' }}</td>@endif
                            <td>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">{{ setting('currency_symbol') }}</span>
                                    <input type="number" step="0.01" min="0" name="prices[{{ $item->id }}]" value="{{ $own }}" class="form-control text-end"
                                           placeholder="{{ $provider && $default !== null ? number_format($default, 2, '.', '') : '' }}" aria-label="Price for {{ $item->billingLabel() }}">
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-4">No items.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white text-end">
            <button class="btn btn-primary px-4"><i class="bi bi-save me-1"></i> Save prices</button>
        </div>
    </div>
</form>
@endsection
