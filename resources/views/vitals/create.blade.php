@extends('layouts.app')

@section('title', 'Record Vital Signs')

@section('content')
<div class="mx-auto" style="max-width: 1100px;">
    @include('patients._mini-banner')

    <form method="POST" action="{{ route('vitals.store', $patient) }}" class="card mb-3">
        @csrf
        <div class="card-header">Vital signs</div>
        <div class="card-body">
            @include('vitals._fields')
            <x-form.textarea name="nursing_note" label="Nursing note (optional)" rows="2" />
        </div>
        <div class="card-footer bg-white d-flex justify-content-end gap-2">
            <a href="{{ route('patients.show', $patient) }}" class="btn btn-outline-secondary">Cancel</a>
            <button class="btn btn-primary px-4"><i class="bi bi-heart-pulse me-1"></i> Save</button>
        </div>
    </form>

    @include('vitals._previous')
</div>
@endsection
