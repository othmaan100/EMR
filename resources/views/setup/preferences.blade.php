@extends('setup.layout')

@section('step')
    <p class="text-muted">Regional settings and branding. All of these can be changed later under <strong>Hospital Settings</strong>.</p>
    <form method="POST" action="{{ route('setup.store', 'preferences') }}">
        @csrf
        @include('settings.fields.preferences')
        @include('setup.partials.actions')
    </form>
@endsection
