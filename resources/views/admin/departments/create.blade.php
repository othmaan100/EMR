@extends('layouts.app')

@section('title', 'Add Department')

@section('content')
<form method="POST" action="{{ route('admin.departments.store') }}" class="mx-auto" style="max-width: 800px;">
    @csrf
    @include('admin.departments._form')
    <div class="d-flex justify-content-end gap-2">
        <a href="{{ route('admin.departments.index') }}" class="btn btn-outline-secondary">Cancel</a>
        <button class="btn btn-primary px-4"><i class="bi bi-check-lg me-1"></i> Create department</button>
    </div>
</form>
@endsection
