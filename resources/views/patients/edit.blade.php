@extends('layouts.app')

@section('title', 'Edit Patient — '.$patient->hospital_number)

@section('content')
<form method="POST" action="{{ route('patients.update', $patient) }}" enctype="multipart/form-data">
    @csrf
    @method('PUT')
    @include('patients._form')
    <div class="d-flex justify-content-end gap-2">
        <a href="{{ route('patients.show', $patient) }}" class="btn btn-outline-secondary">Cancel</a>
        <button class="btn btn-primary px-4"><i class="bi bi-check-lg me-1"></i> Save changes</button>
    </div>
</form>
@endsection
