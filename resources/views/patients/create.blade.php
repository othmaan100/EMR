@extends('layouts.app')

@section('title', 'Register Patient')

@section('content')
<form method="POST" action="{{ route('patients.store') }}" enctype="multipart/form-data">
    @csrf
    @include('patients._form')
    <div class="d-flex justify-content-end gap-2">
        <a href="{{ route('patients.index') }}" class="btn btn-outline-secondary">Cancel</a>
        <button class="btn btn-primary px-4"><i class="bi bi-person-plus me-1"></i> Register patient</button>
    </div>
</form>
@endsection
