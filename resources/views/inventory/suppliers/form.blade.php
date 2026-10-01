@extends('layouts.app')

@section('title', $supplier->exists ? 'Edit Supplier' : 'Add Supplier')

@section('content')
<form method="POST" action="{{ $supplier->exists ? route('suppliers.update', $supplier) : route('suppliers.store') }}" class="mx-auto" style="max-width: 760px;">
    @csrf
    @if ($supplier->exists) @method('PUT') @endif
    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-3">
                <x-form.input name="name" label="Supplier name" :value="$supplier->name" required col="col-md-8" autofocus />
                <x-form.input name="contact_person" label="Contact person" :value="$supplier->contact_person" col="col-md-4" />
                <x-form.input name="phone" type="tel" label="Phone" :value="$supplier->phone" col="col-md-6" />
                <x-form.input name="email" type="email" label="Email" :value="$supplier->email" col="col-md-6" />
                <x-form.input name="address" label="Address" :value="$supplier->address" col="col-12" />
                <div class="col-12">
                    <div class="form-check form-switch">
                        <input type="hidden" name="is_active" value="0">
                        <input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" id="is_active" @checked(old('is_active', $supplier->is_active))>
                        <label class="form-check-label" for="is_active">Active</label>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="d-flex justify-content-end gap-2">
        <a href="{{ route('suppliers.index') }}" class="btn btn-outline-secondary">Cancel</a>
        <button class="btn btn-primary px-4"><i class="bi bi-check-lg me-1"></i> Save</button>
    </div>
</form>
@endsection
