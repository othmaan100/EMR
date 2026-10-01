@extends('layouts.app')

@section('title', 'Triage — '.$visit->queue_number)

@section('content')
<div class="mx-auto" style="max-width: 1100px;">
    @include('patients._mini-banner')

    <form method="POST" action="{{ route('vitals.triage.store', $visit) }}" class="card mb-3">
        @csrf
        <div class="card-header">Vital signs</div>
        <div class="card-body">
            @include('vitals._fields')

            <hr>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Triage priority <span class="text-danger">*</span></label>
                    <div class="d-flex gap-2">
                        @foreach (\App\Models\Visit::PRIORITIES as $key => $p)
                            <input type="radio" class="btn-check" name="priority" id="priority-{{ $key }}" value="{{ $key }}" @checked(old('priority', $visit->priority) === $key)>
                            <label class="btn btn-outline-{{ $p['color'] }} flex-fill" for="priority-{{ $key }}">{{ $p['label'] }}</label>
                        @endforeach
                    </div>
                </div>
                <x-form.textarea name="nursing_note" label="Triage note" col="col-md-6" rows="2" placeholder="Presenting complaint, observations, first aid given…" />
            </div>
        </div>
        <div class="card-footer bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div class="form-check">
                <input type="hidden" name="send_to_doctor" value="0">
                <input class="form-check-input" type="checkbox" name="send_to_doctor" value="1" id="send_to_doctor" @checked(old('send_to_doctor', true))>
                <label class="form-check-label" for="send_to_doctor">Send to doctor's queue after saving</label>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('vitals.worklist') }}" class="btn btn-outline-secondary">Cancel</a>
                <button class="btn btn-primary px-4"><i class="bi bi-heart-pulse me-1"></i> Save triage</button>
            </div>
        </div>
    </form>

    @include('vitals._previous')
</div>
@endsection
