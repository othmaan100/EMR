@extends('layouts.app')

@section('title', $ward->exists ? 'Ward — '.$ward->name : 'Add Ward')

@section('content')
<div class="row g-3">
    <div class="col-lg-{{ $ward->exists ? 5 : 8 }} mx-auto">
        <form method="POST" action="{{ $ward->exists ? route('admin.wards.update', $ward) : route('admin.wards.store') }}" class="card">
            @csrf
            @if ($ward->exists) @method('PUT') @endif
            <div class="card-header">Ward details</div>
            <div class="card-body">
                <div class="row g-3">
                    <x-form.input name="name" label="Ward name" :value="$ward->name" required col="col-md-8" autofocus />
                    <x-form.input name="code" label="Code" :value="$ward->code" required col="col-md-4" maxlength="10" />
                    <x-form.select name="type" label="Type" :options="array_combine(\App\Models\Ward::TYPES, \App\Models\Ward::TYPES)" :value="$ward->type" required placeholder="Select..." col="col-md-6" />
                    <x-form.select name="gender" label="Admits" :options="['any' => 'All patients', 'male' => 'Male only', 'female' => 'Female only']" :value="$ward->gender" required col="col-md-6" />
                    <x-form.select name="department_id" label="Department" :options="$departments->all()" :value="$ward->department_id" placeholder="— None —" col="col-md-6" />
                    <x-form.select name="service_id" label="Daily bed charge" :options="$services->all()" :value="$ward->service_id" placeholder="— No charge —" col="col-md-6"
                                   help="An &quot;Accommodation&quot; service; set its price in the Price List." />
                    @unless ($ward->exists)
                        <x-form.input name="bed_count" type="number" label="Number of beds" value="10" col="col-md-6" min="0" max="100" />
                        <x-form.input name="bed_prefix" label="Bed label prefix" col="col-md-6" maxlength="10" placeholder="e.g. A → A1, A2…" />
                    @endunless
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input type="hidden" name="is_active" value="0">
                            <input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" id="is_active" @checked(old('is_active', $ward->is_active))>
                            <label class="form-check-label" for="is_active">Active</label>
                        </div>
                    </div>
                </div>
                @if ($services->isEmpty())
                    <div class="alert alert-info small mt-3 mb-0">
                        To charge for beds, first add a service in category <strong>Accommodation</strong> under Clinical Catalogues → Services.
                    </div>
                @endif
            </div>
            <div class="card-footer bg-white d-flex justify-content-end gap-2">
                <a href="{{ route('admin.wards.index') }}" class="btn btn-outline-secondary">Back</a>
                <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Save</button>
            </div>
        </form>
    </div>

    @if ($ward->exists)
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Beds ({{ $ward->beds->count() }})</span>
                    <form method="POST" action="{{ route('admin.wards.beds', $ward) }}" class="d-flex gap-1">
                        @csrf
                        <input type="number" name="bed_count" value="1" min="1" max="100" class="form-control form-control-sm" style="width: 5rem;" aria-label="Beds to add">
                        <input type="text" name="bed_prefix" maxlength="10" class="form-control form-control-sm" style="width: 6rem;" placeholder="Prefix" aria-label="Prefix">
                        <button class="btn btn-sm btn-outline-primary text-nowrap">Add beds</button>
                    </form>
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        @forelse ($ward->beds as $bed)
                            @php $s = \App\Models\Bed::STATUSES[$bed->status]; @endphp
                            <div class="col-sm-6 col-xl-4">
                                <div class="border rounded p-2 h-100 border-{{ $s['color'] }}">
                                    <div class="d-flex justify-content-between">
                                        <strong>{{ $bed->label }}</strong>
                                        <span class="badge text-bg-{{ $s['color'] }}"><i class="bi {{ $s['icon'] }}"></i> {{ $s['label'] }}</span>
                                    </div>
                                    @if ($bed->currentAdmission)
                                        <div class="small text-truncate">{{ $bed->currentAdmission->patient->list_name }}</div>
                                    @else
                                        <form method="POST" action="{{ route('admin.beds.status', $bed) }}" class="mt-1">
                                            @csrf @method('PATCH')
                                            <select name="status" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="Bed status">
                                                @foreach (['available', 'cleaning', 'out_of_service'] as $st)
                                                    <option value="{{ $st }}" @selected($bed->status === $st)>{{ \App\Models\Bed::STATUSES[$st]['label'] }}</option>
                                                @endforeach
                                            </select>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <p class="text-muted mb-0">No beds yet.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
