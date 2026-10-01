@extends('layouts.app')

@section('title', 'Edit Staff — '.$user->name)

@section('content')
<form method="POST" action="{{ route('admin.users.update', $user) }}" class="mx-auto" style="max-width: 900px;">
    @csrf
    @method('PUT')
    @include('admin.users._form')
    <div class="d-flex justify-content-end gap-2">
        <a href="{{ route('admin.users.show', $user) }}" class="btn btn-outline-secondary">Cancel</a>
        <button class="btn btn-primary px-4"><i class="bi bi-check-lg me-1"></i> Save changes</button>
    </div>
</form>
@endsection
