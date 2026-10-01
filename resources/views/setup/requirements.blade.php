@extends('setup.layout')

@section('step')
    <p class="text-muted">We'll check that the server is ready, then create the database tables.</p>

    <ul class="list-group mb-3">
        @foreach ($requirements as $check)
            <li class="list-group-item d-flex justify-content-between align-items-center gap-3">
                <span>
                    <i class="bi {{ $check['ok'] ? 'bi-check-circle-fill text-success' : 'bi-x-circle-fill text-danger' }} me-2"></i>
                    {{ $check['label'] }}
                </span>
                <small class="{{ $check['ok'] ? 'text-muted' : 'text-danger' }} text-end">{{ $check['detail'] }}</small>
            </li>
        @endforeach
    </ul>

    @if (! collect($requirements)->every('ok'))
        <div class="alert alert-warning small mb-0">
            <i class="bi bi-info-circle me-1"></i>
            Fix the items marked in red and refresh this page. Database settings are configured in the <code>.env</code> file
            (<code>DB_HOST</code>, <code>DB_DATABASE</code>, <code>DB_USERNAME</code>, <code>DB_PASSWORD</code>).
        </div>
    @endif

    <form method="POST" action="{{ route('setup.store', 'requirements') }}">
        @csrf
        @include('setup.partials.actions', ['label' => 'Install Database & Continue'])
    </form>
@endsection
