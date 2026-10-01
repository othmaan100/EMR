@extends('layouts.app')

@section('title', 'SMS Messages')

@section('content')
<div class="row g-3 mb-3">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-body">
                <div class="small text-muted">Provider</div>
                <div class="fs-5 fw-semibold mb-2">{{ $provider }}
                    @can('settings.manage')
                        <a href="{{ route('admin.settings.edit', ['section' => 'sms']) }}" class="small fw-normal ms-2">Change</a>
                    @endcan
                </div>
                <div class="d-flex flex-wrap gap-3 small">
                    <span>Today:</span>
                    @foreach (\App\Models\SmsMessage::STATUSES as $key => $s)
                        <span><span class="badge text-bg-{{ $s['color'] }}">{{ $today[$key] ?? 0 }}</span> {{ $s['label'] }}</span>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-body">
                <form method="POST" action="{{ route('admin.sms.test') }}">
                    @csrf
                    <label for="phone" class="form-label">Send a test message</label>
                    <div class="input-group">
                        <input type="tel" id="phone" name="phone" value="{{ old('phone') }}" required maxlength="30" placeholder="e.g. 0803 123 4567"
                               @class(['form-control', 'is-invalid' => $errors->has('phone')])>
                        <button class="btn btn-primary"><i class="bi bi-send me-1"></i> Send</button>
                        @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <form class="row g-2 align-items-center" method="GET">
            <div class="col-md-4"><input type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control form-control-sm" placeholder="Phone or patient" aria-label="Search"></div>
            <div class="col-md-3">
                <select name="type" class="form-select form-select-sm" aria-label="Type">
                    <option value="">All types</option>
                    @foreach (\App\Models\SmsMessage::TYPES as $key => $label)<option value="{{ $key }}" @selected(($filters['type'] ?? '') === $key)>{{ $label }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select form-select-sm" aria-label="Status">
                    <option value="">All statuses</option>
                    @foreach (\App\Models\SmsMessage::STATUSES as $key => $s)<option value="{{ $key }}" @selected(($filters['status'] ?? '') === $key)>{{ $s['label'] }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-2"><button class="btn btn-sm btn-outline-secondary w-100">Filter</button></div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
                <tr><th class="ps-3">When</th><th>To</th><th>Type</th><th>Message</th><th>Status</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($messages as $m)
                    <tr>
                        <td class="ps-3 small text-nowrap">{{ format_date($m->created_at, true) }}</td>
                        <td class="small">
                            {{ $m->phone }}
                            @if ($m->patient)<div class="text-muted">{{ $m->patient->full_name }}</div>@endif
                        </td>
                        <td class="small">{{ \App\Models\SmsMessage::TYPES[$m->type] ?? $m->type }}</td>
                        <td class="small" style="max-width: 28rem;">{{ $m->body }}</td>
                        <td class="small">
                            <span class="badge text-bg-{{ \App\Models\SmsMessage::STATUSES[$m->status]['color'] }}">{{ \App\Models\SmsMessage::STATUSES[$m->status]['label'] }}</span>
                            @if ($m->error)<div class="text-danger">{{ $m->error }}</div>@endif
                        </td>
                        <td class="text-end pe-3">
                            @if ($m->status === 'failed')
                                <form method="POST" action="{{ route('admin.sms.retry', $m) }}">@csrf
                                    <button class="btn btn-sm btn-outline-secondary">Retry</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">No messages yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($messages->hasPages())<div class="card-footer bg-white">{{ $messages->links() }}</div>@endif
</div>
@endsection
