@extends('layouts.app')

@section('title', 'Integration message')

@section('content')
<a href="{{ route('admin.integrations.index', ['channel' => $message->channel]) }}" class="small text-decoration-none"><i class="bi bi-arrow-left"></i> Integrations</a>
<div class="card mt-2">
    <div class="card-header">{{ \App\Models\IntegrationMessage::CHANNELS[$message->channel] ?? $message->channel }} · {{ format_date($message->created_at, true) }} · {{ $message->status }}</div>
    <div class="card-body">
        <p>{{ $message->summary }}</p>
        <pre class="bg-light border rounded p-3 small mb-0" style="white-space: pre-wrap; max-height: 60vh; overflow: auto;">{{ str_replace("\r", "\n", (string) $message->payload) }}</pre>
    </div>
</div>
@endsection
