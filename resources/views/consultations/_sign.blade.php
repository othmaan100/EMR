<div class="card mb-3 border-success" id="sign">
    <div class="card-header"><i class="bi bi-pen me-1"></i> Finish consultation</div>
    <div class="card-body">
        <form method="POST" action="{{ route('consultations.sign', $consultation) }}"
              onsubmit="return confirm('Sign this consultation? Notes and diagnoses will be locked and the visit completed.')">
            @csrf
            <p class="small text-muted mb-2">Save your notes first. Optionally book a follow-up visit:</p>
            <div class="row g-2 align-items-end">
                <x-form.input name="followup_date" type="date" label="Follow-up date" col="col-md-3" min="{{ today()->addDay()->toDateString() }}" />
                <x-form.input name="followup_time" type="time" label="Time" col="col-md-2" value="09:00" step="300" />
                <x-form.input name="followup_reason" label="Reason" col="col-md-4" placeholder="Defaults to the primary diagnosis" />
                <div class="col-md-3">
                    <button class="btn btn-success w-100"><i class="bi bi-patch-check me-1"></i> Sign & complete</button>
                </div>
            </div>
        </form>
    </div>
</div>
