@extends('layouts.app')

@section('title', 'Add Clinic')

@section('content')
<form method="POST" action="{{ route('admin.clinics.store') }}" class="mx-auto" style="max-width: 800px;">
    @csrf
    @include('admin.clinics._form')
    <div class="d-flex justify-content-end gap-2">
        <a href="{{ route('admin.clinics.index') }}" class="btn btn-outline-secondary">Cancel</a>
        <button class="btn btn-primary px-4"><i class="bi bi-check-lg me-1"></i> Create clinic</button>
    </div>
</form>
@endsection
