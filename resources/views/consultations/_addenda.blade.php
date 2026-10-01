<div class="card mb-3" id="addenda">
    <div class="card-header"><i class="bi bi-journal-plus me-1"></i> Addenda</div>
    <ul class="list-group list-group-flush">
        @forelse ($consultation->addenda as $addendum)
            <li class="list-group-item">
                <div class="small text-muted mb-1">{{ format_date($addendum->created_at, true) }} · {{ $addendum->author?->name }}</div>
                <div style="white-space: pre-line;">{{ $addendum->note }}</div>
            </li>
        @empty
            <li class="list-group-item text-muted small">No addenda. Signed notes cannot be changed; add corrections or late findings here.</li>
        @endforelse
    </ul>
    @can('consultations.create')
        <div class="card-body border-top">
            <form method="POST" action="{{ route('consultations.addenda.store', $consultation) }}">
                @csrf
                <textarea name="note" rows="2" required maxlength="5000" aria-label="Addendum"
                          @class(['form-control mb-2', 'is-invalid' => $errors->addendum->has('note')]) placeholder="Addendum…">{{ old('note') }}</textarea>
                <div class="text-end"><button class="btn btn-sm btn-outline-primary">Add addendum</button></div>
            </form>
        </div>
    @endcan
</div>
