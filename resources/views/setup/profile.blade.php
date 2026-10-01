@extends('setup.layout')

@section('step')
    <p class="text-muted">This information appears across the system — on screens, patient cards, receipts and printed reports.</p>
    <form method="POST" action="{{ route('setup.store', 'profile') }}" enctype="multipart/form-data">
        @csrf
        @include('settings.fields.profile')
        @include('setup.partials.actions')
    </form>
@endsection
