@if ($order->technique)
    <div class="mb-3"><div class="fw-semibold small text-muted text-uppercase">Technique</div><div style="white-space: pre-line;">{{ $order->technique }}</div></div>
@endif
<div class="mb-3"><div class="fw-semibold small text-muted text-uppercase">Findings</div><div style="white-space: pre-line;">{{ $order->findings }}</div></div>
<div class="mb-0 p-2 border-start border-4 border-primary bg-light">
    <div class="fw-semibold small text-muted text-uppercase">Impression</div>
    <div class="fw-semibold" style="white-space: pre-line;">{{ $order->impression }}</div>
</div>
