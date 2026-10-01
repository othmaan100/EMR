<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('layouts.partials.head')
    <meta name="idle-minutes" content="{{ (int) setting('session_idle_minutes') }}">
    <meta name="ping-url" content="{{ route('session.ping') }}">
    <meta name="login-url" content="{{ route('login') }}">
</head>
<body>
    @include('layouts.partials.sidebar')

    <div class="main">
        <header class="topbar gap-3">
            <button class="btn btn-light d-lg-none" type="button" data-toggle-sidebar aria-label="Toggle menu">
                <i class="bi bi-list"></i>
            </button>
            <h1 class="h5 mb-0 text-truncate">@yield('title')</h1>

            @can('patients.view')
                <form action="{{ route('patients.index') }}" method="GET" class="ms-auto d-none d-md-block" role="search" style="width: 320px;">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                        <input type="search" name="q" class="form-control" placeholder="Find patient: name, number, phone…" aria-label="Find patient">
                    </div>
                </form>
            @endcan

            <div class="ms-auto ms-md-3 dropdown">
                <a href="#" class="d-flex align-items-center gap-2 text-decoration-none text-dark" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="avatar">{{ auth()->user()->initials() }}</span>
                    <span class="d-none d-md-block lh-sm">
                        <span class="d-block fw-semibold">{{ auth()->user()->name }}</span>
                        <small class="text-muted">{{ auth()->user()->getRoleNames()->first() ?? auth()->user()->designation }}</small>
                    </span>
                    <i class="bi bi-chevron-down small text-muted"></i>
                </a>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                    <li><h6 class="dropdown-header">{{ auth()->user()->email }}</h6></li>
                    <li><a class="dropdown-item" href="{{ route('account.profile') }}"><i class="bi bi-person-circle me-2"></i>My profile</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i>Sign out</button>
                        </form>
                    </li>
                </ul>
            </div>
        </header>

        <main class="content">
            @include('layouts.partials.flash')
            @yield('content')
        </main>

        <footer class="footer d-flex justify-content-between">
            <span>&copy; {{ date('Y') }} {{ setting('hospital_name') }}</span>
            <span>EMR v{{ config('emr.version') }}</span>
        </footer>
    </div>

    <div class="modal fade" id="idleModal" tabindex="-1" aria-labelledby="idleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="idleModalLabel"><i class="bi bi-hourglass-split me-1"></i> Are you still there?</h5>
                </div>
                <div class="modal-body">
                    To protect patient information you will be signed out in <strong data-countdown>60</strong> seconds.
                    Unsaved changes on this page will be lost.
                </div>
                <div class="modal-footer">
                    <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn btn-outline-secondary">Sign out now</button></form>
                    <button type="button" class="btn btn-primary" data-stay>Stay signed in</button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
