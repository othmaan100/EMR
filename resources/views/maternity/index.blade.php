@extends('layouts.app')

@section('title', 'Maternity')

@section('content')
@php
    $tabs = ['anc' => ['Antenatal', $anc->count(), 'bi-person-heart'], 'labour' => ['In labour', $labour->count(), 'bi-activity'],
             'deliveries' => ['Deliveries (30 days)', $deliveries->count(), 'bi-balloon-heart'], 'postnatal' => ['Postnatal (6 weeks)', $postnatal->count(), 'bi-house-heart']];
@endphp

<ul class="nav nav-pills flex-wrap gap-1 mb-3">
    @foreach ($tabs as $key => [$label, $count, $icon])
        <li class="nav-item">
            <a @class(['nav-link py-1', 'active' => $tab === $key]) href="{{ route('maternity.index', ['tab' => $key]) }}">
                <i class="bi {{ $icon }} me-1"></i>{{ $label }} <span class="badge text-bg-light ms-1">{{ $count }}</span>
            </a>
        </li>
    @endforeach
</ul>

<div class="card">
    <div class="table-responsive">
        @if (in_array($tab, ['anc', 'labour'], true))
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr><th class="ps-3">Patient</th><th>G/P</th><th>Gestation</th><th>EDD</th><th>Last visit</th><th>Next visit</th><th>Risk</th></tr></thead>
                <tbody>
                    @forelse ($tab === 'anc' ? $anc : $labour as $p)
                        @php
                            $last = $p->ancVisits->first();
                            $next = $last?->next_visit;
                        @endphp
                        <tr data-href="{{ route($tab === 'labour' ? 'maternity.partograph' : 'maternity.show', $p) }}">
                            <td class="ps-3">{{ $p->patient->list_name }}<small class="d-block text-muted">{{ $p->patient->hospital_number }} · {{ $p->patient->age }}</small></td>
                            <td>{{ $p->obstetricFormula() }}</td>
                            <td class="fw-semibold">{{ $p->gestationLabel() }}</td>
                            <td @class(['text-danger fw-semibold' => $p->edd->isPast()])>{{ format_date($p->edd) }}</td>
                            <td class="small">{{ $last ? format_date($last->visit_date) : 'None yet' }}</td>
                            <td class="small">
                                @if ($next)<span @class(['text-danger fw-semibold' => $next->isPast()])>{{ format_date($next) }}{{ $next->isPast() ? ' — missed' : '' }}</span>@else — @endif
                            </td>
                            <td>@if ($p->isHighRisk())<span class="badge text-bg-danger"><i class="bi bi-exclamation-triangle"></i> High risk</span>@else<span class="badge text-bg-light border">Routine</span>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">Nobody here.</td></tr>
                    @endforelse
                </tbody>
            </table>
        @elseif ($tab === 'deliveries')
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr><th class="ps-3">Date</th><th>Mother</th><th>Mode</th><th>Babies</th><th>Blood loss</th><th>Complications</th></tr></thead>
                <tbody>
                    @forelse ($deliveries as $d)
                        <tr data-href="{{ route('maternity.show', $d->pregnancy) }}">
                            <td class="ps-3 small">{{ format_date($d->delivered_at, true) }}</td>
                            <td>{{ $d->pregnancy->patient->list_name }}</td>
                            <td class="small">{{ $d->modeLabel() }}</td>
                            <td class="small">
                                @foreach ($d->babies as $b)
                                    <div>{{ ucfirst($b->sex) }} · {{ $b->birth_weight_g ? $b->birth_weight_g.' g' : '—' }} · {{ $b->outcomeLabel() }}</div>
                                @endforeach
                            </td>
                            <td>{{ $d->blood_loss_ml ? $d->blood_loss_ml.' ml' : '—' }}</td>
                            <td class="small">{{ collect($d->complications)->map(fn ($c) => config("emr.maternity.complications.$c"))->implode(', ') ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">No deliveries in the last 30 days.</td></tr>
                    @endforelse
                </tbody>
            </table>
        @else
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr><th class="ps-3">Mother</th><th>Delivered</th><th>Days postpartum</th><th>Postnatal visits</th></tr></thead>
                <tbody>
                    @forelse ($postnatal as $p)
                        <tr data-href="{{ route('maternity.show', $p) }}">
                            <td class="ps-3">{{ $p->patient->list_name }}</td>
                            <td class="small">{{ format_date($p->delivery->delivered_at) }}</td>
                            <td>{{ (int) $p->delivery->delivered_at->startOfDay()->diffInDays(today()) }}</td>
                            <td>{{ $p->postnatalVisits->count() }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-4">No mothers in the postnatal period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        @endif
    </div>
</div>
<p class="small text-muted mt-2">To book a new pregnancy, open the patient's folder and choose <strong>Book ANC</strong>.</p>
@endsection
