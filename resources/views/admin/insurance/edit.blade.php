@extends('layouts.app')

@section('title', 'Edit — '.$provider->name)

@section('content')
<form method="POST" action="{{ route('admin.insurance.update', $provider) }}" class="mx-auto" style="max-width: 800px;">
    @csrf
    @method('PUT')
    @include('admin.insurance._form')
    <div class="d-flex justify-content-end gap-2">
        <a href="{{ route('admin.insurance.index') }}" class="btn btn-outline-secondary">Cancel</a>
        <button class="btn btn-primary px-4"><i class="bi bi-check-lg me-1"></i> Save changes</button>
    </div>
</form>
@endsection
