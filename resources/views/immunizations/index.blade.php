@extends('layouts.app')

@section('title', 'Immunization — Defaulters')

@section('content')
<p class="text-muted">Children under 5 (born here or with any dose recorded) who have a dose more than {{ \App\Services\ImmunizationService::GRACE_DAYS }} days overdue.
    Call the carer to bring the child back. To record doses for any patient, open their folder → <strong>Immunizations</strong>.</p>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th class="ps-3">Child</th><th>Age</th><th>Carer / phone</th><th>Overdue doses</th><th></th></tr></thead>
            <tbody>
                @forelse ($defaulters as $row)
                    @php $p = $row['patient']; @endphp
                    <tr>
                        <td class="ps-3">{{ $p->list_name }}<small class="d-block text-muted">{{ $p->hospital_number }}</small></td>
                        <td>{{ $p->age }}</td>
                        <td class="small">{{ $p->nok_name ?? '—' }}<span class="d-block">{{ $p->phone ?? $p->nok_phone ?? '—' }}</span></td>
                        <td class="small">
                            @foreach ($row['overdue'] as $dose)
                                <span class="badge text-bg-danger mb-1">{{ $dose['vaccine']->label }} · due {{ format_date($dose['due']) }}</span>
                            @endforeach
                        </td>
                        <td class="text-end pe-3"><a href="{{ route('immunizations.show', $p) }}" class="btn btn-sm btn-primary">Open</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-5"><i class="bi bi-emoji-smile fs-2 d-block mb-2"></i>No defaulters. Well done!</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
