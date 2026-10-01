@extends('layouts.app')

@section('title', 'Hospital Settings')

@section('content')
@php
    $tabs = [
        'profile' => ['Hospital Profile', 'bi-hospital'],
        'contact' => ['Contact & Address', 'bi-geo-alt'],
        'preferences' => ['Preferences', 'bi-sliders'],
        'security' => ['Security & backups', 'bi-shield-lock'],
        'sms' => ['SMS messaging', 'bi-chat-dots'],
        'integrations' => ['Integrations', 'bi-plug'],
    ];
@endphp
<div class="row g-3">
    <div class="col-lg-3">
        <div class="card">
            <div class="card-body p-2">
                <nav class="nav nav-pills flex-column">
                    @foreach ($tabs as $key => [$label, $icon])
                        <a href="{{ route('admin.settings.edit', ['section' => $key]) }}" @class(['nav-link', 'active' => $section === $key])>
                            <i class="bi {{ $icon }} me-2"></i>{{ $label }}
                        </a>
                    @endforeach
                </nav>
            </div>
        </div>
    </div>
    <div class="col-lg-9">
        <div class="card">
            <div class="card-header py-3">{{ $tabs[$section][0] }}</div>
            <div class="card-body p-4">
                <form method="POST" action="{{ route('admin.settings.update', $section) }}" enctype="multipart/form-data">
                    @csrf
                    @include('settings.fields.'.$section)
                    <div class="mt-4 pt-3 border-top text-end">
                        <button class="btn btn-primary px-4"><i class="bi bi-check-lg me-1"></i> Save changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
