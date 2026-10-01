@extends('layouts.app')

@section('title', 'Reschedule Appointment')

@section('content')
<form method="POST" action="{{ route('appointments.update', $appointment) }}" class="mx-auto" style="max-width: 800px;">
    @csrf
    @method('PUT')
    @include('appointments._form')
    <div class="d-flex justify-content-end gap-2">
        <a href="{{ route('appointments.index', ['date' => $appointment->scheduled_at->toDateString()]) }}" class="btn btn-outline-secondary">Cancel</a>
        <button class="btn btn-primary px-4"><i class="bi bi-check-lg me-1"></i> Save changes</button>
    </div>
</form>
@endsection
