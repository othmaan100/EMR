{{--
    Compact vitals readout with abnormal flags.
    Flags carry an icon + text, never colour alone.
    @param VitalSign $v
    @param bool $compact  (one line, for queue cards)
--}}
@php
    $flags = $v->flags();
    $risk = $v->news2Risk();
    $compact = $compact ?? false;
    $icon = ['low' => 'bi-arrow-down', 'high' => 'bi-arrow-up', 'critical' => 'bi-exclamation-octagon-fill'];
    $cls = ['low' => 'text-info-emphasis', 'high' => 'text-warning-emphasis', 'critical' => 'text-danger fw-bold'];
    $items = array_filter([
        'bp' => $v->bloodPressure() ? ['BP', $v->bloodPressure().' mmHg', $flags['systolic'] ?? $flags['diastolic'] ?? null] : null,
        'temperature' => $v->temperature !== null ? ['Temp', $v->display('temperature'), $flags['temperature'] ?? null] : null,
        'pulse' => $v->pulse !== null ? ['Pulse', $v->display('pulse'), $flags['pulse'] ?? null] : null,
        'respiratory_rate' => $v->respiratory_rate !== null ? ['RR', $v->display('respiratory_rate'), $flags['respiratory_rate'] ?? null] : null,
        'spo2' => $v->spo2 !== null ? ['SpO₂', $v->display('spo2').($v->on_oxygen ? ' on O₂' : ''), $flags['spo2'] ?? null] : null,
        'weight' => ! $compact && $v->weight !== null ? ['Weight', $v->display('weight'), null] : null,
        'bmi' => ! $compact && $v->bmi !== null ? ['BMI', number_format($v->bmi, 1), $flags['bmi'] ?? null] : null,
        'pain_score' => ! $compact && $v->pain_score !== null ? ['Pain', $v->pain_score.'/10', $flags['pain_score'] ?? null] : null,
        'blood_glucose' => ! $compact && $v->blood_glucose !== null ? ['Glucose', $v->display('blood_glucose'), $flags['blood_glucose'] ?? null] : null,
        'consciousness' => ! $compact && $v->consciousness ? ['AVPU', \App\Models\VitalSign::CONSCIOUSNESS[$v->consciousness], $flags['consciousness'] ?? null] : null,
    ]);
@endphp

@if ($compact)
    <div class="small d-flex flex-wrap gap-2 mt-1">
        @foreach ($items as [$label, $value, $flag])
            <span @class([$cls[$flag] ?? 'text-body-secondary'])>
                @if ($flag)<i class="bi {{ $icon[$flag] }}" aria-label="{{ $flag }}"></i>@endif{{ $label }} {{ $value }}
            </span>
        @endforeach
        @if ($risk && $risk['score'] > 0)
            <span class="badge text-bg-{{ $risk['color'] }}">NEWS2 {{ $risk['score'] }}</span>
        @endif
    </div>
@else
    <div class="row g-2">
        @foreach ($items as [$label, $value, $flag])
            <div class="col-6 col-md-4 col-xl-3">
                <div @class(['border rounded p-2 h-100', 'border-danger bg-danger-subtle' => $flag === 'critical', 'border-warning bg-warning-subtle' => $flag === 'high', 'border-info bg-info-subtle' => $flag === 'low'])>
                    <div class="small text-muted">{{ $label }}</div>
                    <div @class(['fw-semibold', $cls[$flag] ?? ''])>
                        {{ $value }}
                        @if ($flag)<i class="bi {{ $icon[$flag] }} ms-1"></i><span class="small fw-normal ms-1">{{ ucfirst($flag) }}</span>@endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
    @if ($risk)
        <div class="alert alert-{{ $risk['color'] }} py-2 mt-2 mb-0 small d-flex gap-2 align-items-center">
            <strong>NEWS2 {{ $risk['score'] }}</strong> — {{ $risk['label'] }} clinical risk. {{ $risk['advice'] }}
        </div>
    @endif
@endif
