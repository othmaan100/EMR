@extends('layouts.app')

@section('title', 'Edit Role — '.$role->name)

@section('content')
<form method="POST" action="{{ route('admin.roles.update', $role) }}" class="mx-auto" style="max-width: 900px;">
    @csrf
    @method('PUT')
    @include('admin.roles._form')
    <div class="d-flex justify-content-end gap-2">
        <a href="{{ route('admin.roles.index') }}" class="btn btn-outline-secondary">Cancel</a>
        <button class="btn btn-primary px-4"><i class="bi bi-check-lg me-1"></i> Save permissions</button>
    </div>
</form>
@endsection
