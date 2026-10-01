@php
    $user = auth()->user();
    $canLab = $editable && $user->can('lab.request');
    $canImaging = $editable && $user->can('imaging.request');
    $canRx = $editable && $user->can('prescriptions.create');
    $activeTab = $errors->imaging->any() ? 'imaging' : ($errors->rx->any() ? 'rx' : ($errors->lab->any() ? 'lab' : 'rx'));
    $labCount = $consultation->labOrders->where('status', '!=', 'cancelled')->sum(fn ($o) => $o->items->count());
    $imgCount = $consultation->imagingOrders->where('status', '!=', 'cancelled')->count();
    $rxCount = $consultation->prescriptions->sum(fn ($p) => $p->items->count());
@endphp
<div class="card mb-3" id="orders">
    <div class="card-header pb-0 border-bottom-0">
        <ul class="nav nav-tabs card-header-tabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button @class(['nav-link', 'active' => $activeTab === 'rx']) data-bs-toggle="tab" data-bs-target="#tab-rx" type="button" role="tab">
                    <i class="bi bi-capsule me-1"></i> Prescription <span class="badge text-bg-light">{{ $rxCount }}</span></button>
            </li>
            <li class="nav-item" role="presentation">
                <button @class(['nav-link', 'active' => $activeTab === 'lab']) data-bs-toggle="tab" data-bs-target="#tab-lab" type="button" role="tab">
                    <i class="bi bi-droplet-half me-1"></i> Laboratory <span class="badge text-bg-light">{{ $labCount }}</span></button>
            </li>
            <li class="nav-item" role="presentation">
                <button @class(['nav-link', 'active' => $activeTab === 'imaging']) data-bs-toggle="tab" data-bs-target="#tab-imaging" type="button" role="tab">
                    <i class="bi bi-radioactive me-1"></i> Imaging <span class="badge text-bg-light">{{ $imgCount }}</span></button>
            </li>
        </ul>
    </div>
    <div class="card-body tab-content">
        {{-- ================= Prescription ================= --}}
        <div @class(['tab-pane fade', 'show active' => $activeTab === 'rx']) id="tab-rx" role="tabpanel">
            @foreach ($consultation->prescriptions as $rx)
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="small"><strong>{{ $rx->prescription_number }}</strong>
                        <span class="badge text-bg-{{ $rx->statusColor() }}">{{ $rx->statusLabel() }}</span></span>
                    <a href="{{ route('prescriptions.print', $rx) }}" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer me-1"></i> Print</a>
                </div>
                <div class="table-responsive mb-3">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light"><tr><th>Drug</th><th>Directions</th><th>Qty</th><th>Instructions</th><th></th></tr></thead>
                        <tbody>
                            @foreach ($rx->items as $item)
                                <tr>
                                    <td class="fw-semibold">{{ $item->drug_name }}
                                        @if (! $item->drug_id)<span class="badge text-bg-light border" title="Not in formulary">free text</span>@endif
                                        @if ($item->allergy_override)<span class="badge text-bg-danger" title="Prescribed despite recorded allergy"><i class="bi bi-exclamation-triangle"></i> allergy override</span>@endif
                                    </td>
                                    <td>{{ $item->directions() }}</td>
                                    <td>{{ $item->quantity ?? '—' }}</td>
                                    <td class="small">{{ $item->instructions }}</td>
                                    <td class="text-end">
                                        @if ($canRx && $rx->status === 'pending')
                                            <form method="POST" action="{{ route('prescription-items.destroy', $item) }}">
                                                @csrf @method('DELETE')
                                                <button class="btn btn-sm btn-link text-danger p-0" aria-label="Remove"><i class="bi bi-x-lg"></i></button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endforeach
            @if ($consultation->prescriptions->isEmpty())<p class="text-muted small">No medication prescribed.</p>@endif

            @if ($canRx)
                <form method="POST" action="{{ route('consultations.prescribe', $consultation) }}" class="border rounded p-3 bg-light">
                    @csrf
                    @if ($errors->rx->has('drug') && str_starts_with($errors->rx->first('drug'), 'ALLERGY'))
                        <div class="alert alert-danger py-2">
                            <i class="bi bi-exclamation-octagon-fill me-1"></i> {{ $errors->rx->first('drug') }}
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" name="allergy_override" value="1" id="allergy_override">
                                <label class="form-check-label fw-semibold" for="allergy_override">I have reviewed the allergy and still want to prescribe</label>
                            </div>
                        </div>
                    @elseif ($errors->rx->any())
                        <div class="alert alert-danger py-2 small">{{ $errors->rx->first() }}</div>
                    @endif
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label for="rx_drug" class="form-label small mb-1">Drug</label>
                            <input type="text" id="rx_drug" name="drug" list="drug-list" required value="{{ old('drug') }}" autocomplete="off"
                                   class="form-control" placeholder="Start typing… (formulary or free text)">
                            <datalist id="drug-list">
                                @foreach ($drugs as $d)
                                    <option value="{{ $d->label }}" data-route="{{ $d->route }}"></option>
                                @endforeach
                            </datalist>
                        </div>
                        <div class="col-md-3">
                            <label for="rx_dose" class="form-label small mb-1">Dose</label>
                            <input type="text" id="rx_dose" name="dose" required maxlength="50" value="{{ old('dose') }}" class="form-control" placeholder="e.g. 500mg, 2 tabs, 10ml">
                        </div>
                        <div class="col-md-3">
                            <label for="rx_route" class="form-label small mb-1">Route</label>
                            <select id="rx_route" name="route" class="form-select">
                                @foreach (\App\Models\Prescription::ROUTES as $route)
                                    <option @selected(old('route', 'Oral') === $route)>{{ $route }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="rx_frequency" class="form-label small mb-1">Frequency</label>
                            <select id="rx_frequency" name="frequency" class="form-select">
                                @foreach (\App\Models\Prescription::FREQUENCIES as $code => $label)
                                    <option value="{{ $code }}" @selected(old('frequency', 'TDS') === $code)>{{ $code }} — {{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="rx_duration" class="form-label small mb-1">Duration</label>
                            <div class="input-group">
                                <input type="number" id="rx_duration" name="duration_value" min="1" max="365" required value="{{ old('duration_value', 5) }}" class="form-control">
                                <select name="duration_unit" class="form-select" aria-label="Duration unit">
                                    @foreach (['days', 'weeks', 'months', 'dose(s)'] as $unit)
                                        <option @selected(old('duration_unit', 'days') === $unit)>{{ $unit }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <label for="rx_qty" class="form-label small mb-1">Quantity</label>
                            <input type="number" id="rx_qty" name="quantity" min="1" value="{{ old('quantity') }}" class="form-control" placeholder="optional">
                        </div>
                        <div class="col-md-4">
                            <label for="rx_instructions" class="form-label small mb-1">Instructions</label>
                            <input type="text" id="rx_instructions" name="instructions" maxlength="255" value="{{ old('instructions') }}" class="form-control" placeholder="e.g. after meals">
                        </div>
                    </div>
                    <div class="text-end mt-2"><button class="btn btn-outline-primary"><i class="bi bi-plus-lg me-1"></i> Add to prescription</button></div>
                </form>
                <script>
                    // Default the route from the formulary when a known drug is picked.
                    document.getElementById('rx_drug').addEventListener('change', (e) => {
                        const opt = [...document.querySelectorAll('#drug-list option')].find((o) => o.value === e.target.value);
                        if (opt?.dataset.route) document.getElementById('rx_route').value = opt.dataset.route;
                    });
                </script>
            @endif
        </div>

        {{-- ================= Laboratory ================= --}}
        <div @class(['tab-pane fade', 'show active' => $activeTab === 'lab']) id="tab-lab" role="tabpanel">
            @foreach ($consultation->labOrders as $order)
                <div class="border rounded p-2 mb-2 d-flex justify-content-between align-items-start gap-2">
                    <div>
                        <strong class="small">{{ $order->order_number }}</strong>
                        <span class="badge text-bg-{{ $order->statusColor() }}">{{ $order->statusLabel() }}</span>
                        @if ($order->priority === 'urgent')<span class="badge text-bg-danger">Urgent</span>@endif
                        <div>{{ $order->items->map(fn ($i) => $i->test->name)->implode(', ') }}</div>
                        @if ($order->clinical_notes)<small class="text-muted">{{ $order->clinical_notes }}</small>@endif
                        @if ($order->isReleased() && $user->can('lab.results.view'))
                            <div class="mt-2 border rounded bg-white">@include('laboratory._results')</div>
                        @endif
                    </div>
                    @if ($order->isReleased() && $user->can('lab.results.view'))
                        <div class="text-end text-nowrap">
                            @if ($order->abnormalCount())<span class="badge text-bg-danger d-block mb-1"><i class="bi bi-exclamation-circle"></i> {{ $order->abnormalCount() }} abnormal</span>@endif
                            <a href="{{ route('lab.report', $order) }}" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer"></i> Report</a>
                        </div>
                    @endif
                    @if ($canLab && $order->status === 'requested')
                        <form method="POST" action="{{ route('orders.cancel', ['lab', $order->id]) }}" onsubmit="return confirm('Cancel this lab request?')">
                            @csrf @method('PATCH')
                            <button class="btn btn-sm btn-link text-danger p-0">Cancel</button>
                        </form>
                    @endif
                </div>
            @endforeach
            @if ($consultation->labOrders->isEmpty())<p class="text-muted small">No lab tests requested.</p>@endif

            @if ($canLab)
                <form method="POST" action="{{ route('consultations.lab.store', $consultation) }}" class="border rounded p-3 bg-light">
                    @csrf
                    @if ($errors->lab->any())<div class="alert alert-danger py-2 small">{{ $errors->lab->first() }}</div>@endif
                    <input type="search" class="form-control form-control-sm mb-2" placeholder="Filter tests…" data-filter-list="#lab-test-list" aria-label="Filter tests">
                    <div id="lab-test-list" class="row g-2" style="max-height: 280px; overflow-y: auto;">
                        @foreach ($labTests as $category => $tests)
                            <div class="col-md-6" data-filter-group>
                                <div class="small fw-semibold text-muted text-uppercase mt-1">{{ $category }}</div>
                                @foreach ($tests as $test)
                                    <div class="form-check" data-filter-item>
                                        <input class="form-check-input" type="checkbox" name="tests[]" value="{{ $test->id }}" id="test-{{ $test->id }}"
                                               @checked(in_array($test->id, old('tests', [])))>
                                        <label class="form-check-label small" for="test-{{ $test->id }}">{{ $test->name }}</label>
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                    <div class="row g-2 mt-2 align-items-end">
                        <div class="col-md-3">
                            <label for="lab_priority" class="form-label small mb-1">Priority</label>
                            <select id="lab_priority" name="priority" class="form-select"><option value="routine">Routine</option><option value="urgent">Urgent</option></select>
                        </div>
                        <div class="col-md-6">
                            <label for="lab_notes" class="form-label small mb-1">Clinical notes for the lab</label>
                            <input type="text" id="lab_notes" name="clinical_notes" maxlength="1000" class="form-control" placeholder="e.g. ?malaria, on antibiotics">
                        </div>
                        <div class="col-md-3 text-end"><button class="btn btn-outline-primary w-100"><i class="bi bi-send me-1"></i> Request</button></div>
                    </div>
                </form>
            @endif
        </div>

        {{-- ================= Imaging ================= --}}
        <div @class(['tab-pane fade', 'show active' => $activeTab === 'imaging']) id="tab-imaging" role="tabpanel">
            @foreach ($consultation->imagingOrders as $order)
                <div class="border rounded p-2 mb-2 d-flex justify-content-between align-items-start gap-2">
                    <div>
                        <strong class="small">{{ $order->order_number }}</strong>
                        <span class="badge text-bg-{{ $order->statusColor() }}">{{ $order->statusLabel() }}</span>
                        @if ($order->priority === 'urgent')<span class="badge text-bg-danger">Urgent</span>@endif
                        <div>{{ $order->test->name }}</div>
                        <small class="text-muted">{{ $order->clinical_notes }}</small>
                        @if ($order->isReleased() && $user->can('imaging.results.view'))
                            <div class="mt-2 p-2 border-start border-4 border-primary bg-light small">
                                <strong>Impression:</strong> <span style="white-space: pre-line;">{{ $order->impression }}</span>
                            </div>
                        @endif
                    </div>
                    @if ($order->isReleased() && $user->can('imaging.results.view'))
                        <a href="{{ route('radiology.report', $order) }}" target="_blank" class="btn btn-sm btn-outline-secondary text-nowrap"><i class="bi bi-file-medical"></i> Report</a>
                    @endif
                    @if ($canImaging && $order->status === 'requested')
                        <form method="POST" action="{{ route('orders.cancel', ['imaging', $order->id]) }}" onsubmit="return confirm('Cancel this imaging request?')">
                            @csrf @method('PATCH')
                            <button class="btn btn-sm btn-link text-danger p-0">Cancel</button>
                        </form>
                    @endif
                </div>
            @endforeach
            @if ($consultation->imagingOrders->isEmpty())<p class="text-muted small">No imaging requested.</p>@endif

            @if ($canImaging)
                <form method="POST" action="{{ route('consultations.imaging.store', $consultation) }}" class="border rounded p-3 bg-light">
                    @csrf
                    @if ($errors->imaging->any())<div class="alert alert-danger py-2 small">{{ $errors->imaging->first() }}</div>@endif
                    <div class="row g-2 align-items-end">
                        <div class="col-md-5">
                            <label for="imaging_test_id" class="form-label small mb-1">Examination</label>
                            <select id="imaging_test_id" name="imaging_test_id" class="form-select" required>
                                <option value="">Select…</option>
                                @foreach ($imagingTests as $modality => $tests)
                                    <optgroup label="{{ $modality }}">
                                        @foreach ($tests as $test)
                                            <option value="{{ $test->id }}" @selected(old('imaging_test_id') == $test->id)>{{ $test->name }}</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="img_priority" class="form-label small mb-1">Priority</label>
                            <select id="img_priority" name="priority" class="form-select"><option value="routine">Routine</option><option value="urgent">Urgent</option></select>
                        </div>
                        <div class="col-md-5">
                            <label for="img_notes" class="form-label small mb-1">Clinical indication</label>
                            <input type="text" id="img_notes" name="clinical_notes" required maxlength="1000" value="{{ old('clinical_notes') }}" class="form-control" placeholder="e.g. Productive cough 3 weeks, ?PTB">
                        </div>
                    </div>
                    <div class="text-end mt-2"><button class="btn btn-outline-primary"><i class="bi bi-send me-1"></i> Request imaging</button></div>
                </form>
            @endif
        </div>
    </div>
</div>
