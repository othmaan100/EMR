@extends('setup.layout')

@section('step')
    <p class="text-muted">Create the <strong>Super Admin</strong> account. This user has full access and can create other staff accounts.</p>
    <form method="POST" action="{{ route('setup.store', 'admin') }}">
        @csrf
        <div class="row g-3">
            <x-form.input name="name" label="Full name" required col="col-md-6" autofocus />
            <x-form.input name="username" label="Username" required col="col-md-6" autocomplete="username" help="Letters, numbers, dashes and underscores." />
            <x-form.input name="email" type="email" label="Email" required col="col-md-6" />
            <x-form.input name="phone" type="tel" label="Phone" col="col-md-6" />
            <x-form.input name="password" type="password" label="Password" required col="col-md-6" autocomplete="new-password" help="At least 8 characters with letters and numbers." />
            <x-form.input name="password_confirmation" type="password" label="Confirm password" required col="col-md-6" autocomplete="new-password" />
        </div>
        @include('setup.partials.actions', ['label' => 'Finish Setup'])
    </form>
@endsection
