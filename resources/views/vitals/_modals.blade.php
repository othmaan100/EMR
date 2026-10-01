@can('nursing_notes.create')
    <div class="modal fade" id="noteModal" tabindex="-1" aria-labelledby="noteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" action="{{ route('nursing-notes.store', $patient) }}" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="noteModalLabel">Add nursing note</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="note_type" class="form-label">Type</label>
                        <select id="note_type" name="type" class="form-select">
                            @foreach (\App\Models\NursingNote::TYPES as $key => $label)
                                <option value="{{ $key }}" @selected(old('type', 'general') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <label for="note_text" class="form-label">Note</label>
                    <textarea id="note_text" name="note" rows="5" required maxlength="2000"
                              @class(['form-control', 'is-invalid' => $errors->note->has('note')])>{{ old('note') }}</textarea>
                    @if ($errors->note->has('note'))<div class="invalid-feedback">{{ $errors->note->first('note') }}</div>@endif
                    <div class="form-text">Notes cannot be edited once saved. To correct one, add a new note.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-primary">Save note</button>
                </div>
            </form>
        </div>
    </div>
    @if ($errors->note->any())
        <script>document.addEventListener('DOMContentLoaded', () => new bootstrap.Modal('#noteModal').show());</script>
    @endif
@endcan

<div class="modal fade" id="voidModal" tabindex="-1" aria-labelledby="voidModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            @csrf @method('PATCH')
            <div class="modal-header">
                <h5 class="modal-title" id="voidModalLabel">Mark reading as entered in error</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted">The reading stays in the record (struck through) but is excluded from charts and scores.</p>
                <label for="void_reason" class="form-label">Reason</label>
                <input type="text" id="void_reason" name="void_reason" class="form-control" required maxlength="255" placeholder="e.g. Recorded on wrong patient">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-danger">Mark as error</button>
            </div>
        </form>
    </div>
</div>
<script>
    document.getElementById('voidModal').addEventListener('show.bs.modal', (e) => {
        e.target.querySelector('form').action = e.relatedTarget.dataset.action;
    });
</script>
