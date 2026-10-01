@extends('layouts.app')

@section('title', 'Add Staff')

@section('content')
<form method="POST" action="{{ route('admin.users.store') }}" class="mx-auto" style="max-width: 900px;">
    @csrf
    @include('admin.users._form')
    <div class="d-flex justify-content-end gap-2">
        <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">Cancel</a>
        <button class="btn btn-primary px-4"><i class="bi bi-check-lg me-1"></i> Create account</button>
    </div>
</form>
@endsection
