@php
    $brand = setting('primary_color');
    [$r, $g, $b] = sscanf($brand, '#%02x%02x%02x') + [13, 110, 253];
@endphp
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@hasSection('title')@yield('title') · @endif{{ setting('hospital_short_name') ?: setting('hospital_name') }}</title>
@if (setting('logo'))
    <link rel="icon" href="{{ asset(setting('logo')) }}">
@endif
@vite(['resources/css/app.css', 'resources/js/app.js'])
<style>:root { --brand: {{ $brand }}; --brand-rgb: {{ $r }}, {{ $g }}, {{ $b }}; }</style>
