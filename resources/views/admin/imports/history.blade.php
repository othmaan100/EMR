@extends('layouts.app')

@section('title', 'Import history')

@section('content')
<a href="{{ route('admin.imports.index') }}" class="small text-decoration-none"><i class="bi bi-arrow-left"></i> Data Import</a>
<div class="card mt-2">
    @include('admin.imports._table', ['imports' => $imports])
    @if ($imports->hasPages())<div class="card-footer bg-white">{{ $imports->links() }}</div>@endif
</div>
@endsection
