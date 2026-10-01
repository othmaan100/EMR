@extends('layouts.app')

@section('title', 'Edit Department — '.$department->name)

@section('content')
<form method="POST" action="{{ route('admin.departments.update', $department) }}" class="mx-auto" style="max-width: 800px;">
    @csrf
    @method('PUT')
    @include('admin.departments._form')
    <div class="d-flex justify-content-end gap-2">
        <a href="{{ route('admin.departments.index') }}" class="btn btn-outline-secondary">Cancel</a>
        <button class="btn btn-primary px-4"><i class="bi bi-check-lg me-1"></i> Save changes</button>
    </div>
</form>
@endsection
