<div class="letterhead d-flex gap-3 align-items-center">
    @if (setting('logo'))<img src="{{ asset(setting('logo')) }}" alt="" style="height:56px;width:56px;object-fit:contain">@endif
    <div class="flex-grow-1">
        <div class="h5 mb-0">{{ setting('hospital_name') }}</div>
        <div class="small text-muted">{{ collect([setting('address'), setting('city'), setting('state')])->filter()->implode(', ') }}</div>
        <div class="small text-muted">{{ collect([setting('phone'), setting('email')])->filter()->implode(' · ') }}</div>
    </div>
    <div class="text-end">
        <div class="fw-semibold">{{ $docTitle }}</div>
        <div class="small">{{ $docNumber }}</div>
        <div class="small">{{ $docDate }}</div>
    </div>
</div>
