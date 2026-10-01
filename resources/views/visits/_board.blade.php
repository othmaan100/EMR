@php
    $actions = [
        \App\Models\Visit::WAITING_TRIAGE => [\App\Models\Visit::WAITING_DOCTOR => ['Send to doctor', 'bi-arrow-right-circle', 'outline-primary']],
        \App\Models\Visit::WAITING_DOCTOR => [\App\Models\Visit::IN_CONSULTATION => ['Start consultation', 'bi-play-circle', 'primary']],
        \App\Models\Visit::IN_CONSULTATION => [\App\Models\Visit::COMPLETED => ['Complete', 'bi-check2-circle', 'success']],
    ];
    $canRecordVitals = auth()->user()->can('vitals.record');
    $canConsult = auth()->user()->can('consultations.create');
    $secondary = [
        \App\Models\Visit::WAITING_TRIAGE => ['Back to triage'],
        \App\Models\Visit::WAITING_DOCTOR => ['Return to doctor queue'],
        \App\Models\Visit::LEFT => ['Left without being seen'],
        \App\Models\Visit::CANCELLED => ['Cancel visit'],
    ];
    $canManage = auth()->user()->can('queue.manage');
@endphp
<div class="row g-3">
    @foreach ($columns as $status => $visits)
        @php($meta = \App\Models\Visit::STATUSES[$status])
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center border-top border-3 border-{{ $meta['color'] }}">
                    <span><i class="bi {{ $meta['icon'] }} me-1"></i> {{ $meta['label'] }}</span>
                    <span class="badge text-bg-{{ $meta['color'] }}">{{ $visits->count() }}</span>
                </div>
                <div class="card-body p-2 bg-light" style="min-height: 200px;">
                    @forelse ($visits as $visit)
                        @php($wait = $visit->minutesInStage())
                        <div class="card mb-2 {{ $visit->priority === 'emergency' ? 'border border-danger' : '' }}">
                            <div class="card-body p-2">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <span class="badge bg-brand fs-6">{{ $visit->queue_number }}</span>
                                        @if ($visit->priority !== 'normal')
                                            <span class="badge text-bg-{{ \App\Models\Visit::PRIORITIES[$visit->priority]['color'] }}">{{ \App\Models\Visit::PRIORITIES[$visit->priority]['label'] }}</span>
                                        @endif
                                    </div>
                                    <small @class(['text-nowrap', 'text-danger fw-semibold' => $wait >= 60, 'text-warning' => $wait >= 30 && $wait < 60, 'text-muted' => $wait < 30])
                                           title="Time in this stage"><i class="bi bi-clock"></i> {{ $wait < 60 ? $wait.'m' : intdiv($wait, 60).'h '.($wait % 60).'m' }}</small>
                                </div>
                                <a href="{{ route('patients.show', $visit->patient) }}" class="fw-semibold d-block mt-1 text-decoration-none">{{ $visit->patient->list_name }}</a>
                                <div class="small text-muted">
                                    {{ $visit->patient->hospital_number }} · {{ ucfirst($visit->patient->gender) }} · {{ $visit->patient->age ?? '?' }}
                                    @unless ($clinicId) · {{ $visit->clinic->code }} @endunless
                                </div>
                                @if ($visit->complaint)<div class="small mt-1"><i class="bi bi-chat-left-text me-1"></i>{{ Str::limit($visit->complaint, 70) }}</div>@endif
                                @if ($visit->doctor)<div class="small text-muted"><i class="bi bi-person-badge me-1"></i>{{ $visit->doctor->name }}</div>@endif
                                @if ($visit->latestVitals)
                                    @include('vitals._summary', ['v' => $visit->latestVitals, 'compact' => true])
                                @endif

                                @if ($canManage)
                                    <div class="d-flex gap-1 mt-2">
                                        @if ($status === \App\Models\Visit::WAITING_TRIAGE && $canRecordVitals)
                                            <a href="{{ route('vitals.triage', $visit) }}" class="btn btn-sm btn-primary flex-fill"><i class="bi bi-heart-pulse me-1"></i>Take vitals</a>
                                        @endif
                                        @if ($status === \App\Models\Visit::IN_CONSULTATION && $canConsult)
                                            <a href="{{ route('consultations.show', $visit) }}" class="btn btn-sm btn-primary flex-fill"><i class="bi bi-clipboard2-pulse me-1"></i>Open consultation</a>
                                        @endif
                                        @foreach ($actions[$status] ?? [] as $to => [$label, $icon, $style])
                                            @continue($status === \App\Models\Visit::WAITING_TRIAGE && $canRecordVitals)
                                            {{-- Doctors complete by signing the consultation, not from the board. --}}
                                            @continue($status === \App\Models\Visit::IN_CONSULTATION && $canConsult)
                                            <form method="POST" action="{{ route('visits.move', $visit) }}" class="flex-fill">
                                                @csrf @method('PATCH')
                                                <input type="hidden" name="status" value="{{ $to }}">
                                                <button class="btn btn-sm btn-{{ $style }} w-100"><i class="bi {{ $icon }} me-1"></i>{{ $label }}</button>
                                            </form>
                                        @endforeach
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-light" data-bs-toggle="dropdown" aria-label="More actions"><i class="bi bi-three-dots-vertical"></i></button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                @foreach ($secondary as $to => [$label])
                                                    {{-- Don't repeat the primary button; when "Take vitals" replaced it, offer skipping triage here. --}}
                                                    @php($isPrimary = isset($actions[$status][$to]))
                                                    @php($skipTriage = $isPrimary && $status === \App\Models\Visit::WAITING_TRIAGE && $canRecordVitals)
                                                    @php($label = $skipTriage ? 'Send to doctor (skip vitals)' : $label)
                                                    @if ($visit->canMoveTo($to) && (! $isPrimary || $skipTriage))
                                                        <li>
                                                            <form method="POST" action="{{ route('visits.move', $visit) }}"
                                                                  @if (in_array($to, [\App\Models\Visit::LEFT, \App\Models\Visit::CANCELLED])) onsubmit="return confirm('{{ $label }}?')" @endif>
                                                                @csrf @method('PATCH')
                                                                <input type="hidden" name="status" value="{{ $to }}">
                                                                <button class="dropdown-item">{{ $label }}</button>
                                                            </form>
                                                        </li>
                                                    @endif
                                                @endforeach
                                            </ul>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="text-center text-muted small mt-4 mb-0">No patients</p>
                    @endforelse
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="card mt-3">
    <div class="card-header">Finished today <span class="badge text-bg-light">{{ $done->count() }}</span></div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <tbody>
                @forelse ($done as $visit)
                    <tr>
                        <td class="ps-3"><span class="badge text-bg-light border">{{ $visit->queue_number }}</span></td>
                        <td><a href="{{ route('patients.show', $visit->patient) }}">{{ $visit->patient->list_name }}</a></td>
                        <td class="small">{{ $visit->clinic->name }}</td>
                        <td class="small">{{ $visit->doctor?->name ?? '—' }}</td>
                        <td><span class="badge text-bg-{{ $visit->statusColor() }}">{{ $visit->statusLabel() }}</span></td>
                        <td class="small text-muted text-end pe-3">{{ $visit->completed_at?->format('h:i A') }}</td>
                    </tr>
                @empty
                    <tr><td class="text-center text-muted small py-3">Nothing yet today.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
