@extends('layouts.guest')

@section('title', 'Setup')

@section('content')
<div class="container py-4 py-lg-5">
    <div class="text-center mb-4">
        <span class="stat-icon bg-brand text-white mb-2"><i class="bi bi-hospital"></i></span>
        <h1 class="h3 mb-1">Welcome to Hospital EMR</h1>
        <p class="text-muted mb-0">Let's set up the system for your hospital. This only takes a few minutes.</p>
    </div>

    <div class="row justify-content-center g-4">
        <div class="col-lg-3">
            <div class="card">
                <div class="card-body">
                    <ol class="setup-steps">
                        @foreach ($steps as $key => $info)
                            @php($done = in_array($key, $completed, true))
                            <li @class(['done' => $done && $key !== $step, 'current' => $key === $step])>
                                <span class="step-dot">
                                    @if ($done && $key !== $step)<i class="bi bi-check-lg"></i>@else<i class="bi {{ $info['icon'] }}"></i>@endif
                                </span>
                                @if ($done)
                                    <a href="{{ route('setup.step', $key) }}" class="text-reset text-decoration-none">{{ $info['title'] }}</a>
                                @else
                                    {{ $info['title'] }}
                                @endif
                            </li>
                        @endforeach
                    </ol>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card">
                <div class="card-header py-3">
                    <span class="text-muted small d-block">Step {{ array_search($step, array_keys($steps)) + 1 }} of {{ count($steps) }}</span>
                    {{ $steps[$step]['title'] }}
                </div>
                <div class="card-body p-4">
                    @include('layouts.partials.flash')
                    @yield('step')
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
