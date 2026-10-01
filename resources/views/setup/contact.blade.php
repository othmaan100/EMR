@extends('setup.layout')

@section('step')
    <p class="text-muted">Where patients can find and reach the hospital. Printed on letterheads and receipts.</p>
    <form method="POST" action="{{ route('setup.store', 'contact') }}">
        @csrf
        @include('settings.fields.contact')
        @include('setup.partials.actions')
    </form>
@endsection
