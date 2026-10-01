@extends('layouts.app')

@section('title', 'Book Appointment')

@section('content')
<form method="POST" action="{{ route('appointments.store') }}" class="mx-auto" style="max-width: 800px;">
    @csrf
    @include('appointments._form')
    <div class="d-flex justify-content-end gap-2">
        <a href="{{ url()->previous() }}" class="btn btn-outline-secondary">Cancel</a>
        <button class="btn btn-primary px-4"><i class="bi bi-calendar-plus me-1"></i> Book appointment</button>
    </div>
</form>
@endsection
