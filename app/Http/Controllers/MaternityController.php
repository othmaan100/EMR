<?php

namespace App\Http\Controllers;

use App\Models\Delivery;
use App\Models\Patient;
use App\Models\Pregnancy;
use App\Services\MaternityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MaternityController extends Controller
{
    public function __construct(protected MaternityService $maternity) {}

    public function index(Request $request): View
    {
        $tab = in_array($request->query('tab'), ['anc', 'labour', 'deliveries', 'postnatal'], true) ? $request->query('tab') : 'anc';

        $active = Pregnancy::active()->with(['patient', 'ancVisits'])->orderBy('edd')->get();

        return view('maternity.index', [
            'tab' => $tab,
            'anc' => $active->whereNull('labour_started_at'),
            'labour' => $active->whereNotNull('labour_started_at'),
            'deliveries' => Delivery::with(['pregnancy.patient', 'babies.patient'])->where('delivered_at', '>=', now()->subDays(30))->latest('delivered_at')->get(),
            'postnatal' => Pregnancy::where('status', 'delivered')->with(['patient', 'delivery', 'postnatalVisits'])
                ->whereHas('delivery', fn ($q) => $q->where('delivered_at', '>=', now()->subWeeks(6)))->get(),
        ]);
    }

    public function create(Patient $patient): View
    {
        return view('maternity.book', [
            'patient' => $patient,
            'visit' => null,
            'previous' => $patient->pregnancies()->whereIn('status', ['delivered', 'ended'])->count(),
        ]);
    }

    public function store(Request $request, Patient $patient): RedirectResponse
    {
        $data = $request->validate([
            'lmp' => ['nullable', 'required_without:edd_scan', 'date', 'before_or_equal:today', 'after:'.today()->subDays(310)->toDateString()],
            'edd_scan' => ['nullable', 'date', 'after:today'],
            'gravida' => ['required', 'integer', 'min:1', 'max:30'],
            'parity' => ['required', 'integer', 'min:0', 'max:30', 'lt:gravida'],
            'abortions' => ['required', 'integer', 'min:0', 'max:30'],
            'living_children' => ['required', 'integer', 'min:0', 'max:30'],
            'risk_factors' => ['array'],
            'risk_factors.*' => [Rule::in(array_keys(config('emr.maternity.risk_factors')))],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'lmp.required_without' => 'Enter the last menstrual period, or the EDD from a dating scan.',
            'lmp.after' => 'That LMP is more than 44 weeks ago.',
            'parity.lt' => 'Parity must be lower than gravida (the current pregnancy counts in gravida).',
        ]);

        $pregnancy = $this->maternity->book($patient, $data, $request->user());

        return redirect()->route('maternity.show', $pregnancy)
            ->with('success', "ANC booked. EDD {$pregnancy->edd->format('d M Y')} ({$pregnancy->gestationLabel()} today).");
    }

    public function show(Pregnancy $pregnancy): View
    {
        $pregnancy->load(['patient', 'ancVisits.recorder', 'partograph', 'delivery.babies.patient', 'delivery.attendant', 'postnatalVisits.recorder']);

        return view('maternity.show', ['pregnancy' => $pregnancy, 'patient' => $pregnancy->patient, 'visit' => null]);
    }

    public function storeAnc(Request $request, Pregnancy $pregnancy): RedirectResponse
    {
        $m = config('emr.maternity');
        $data = $request->validateWithBag('anc', [
            'visit_date' => ['required', 'date', 'before_or_equal:today'],
            'weight' => ['nullable', 'numeric', 'between:25,250'],
            'systolic' => ['nullable', 'required_with:diastolic', 'integer', 'between:60,260'],
            'diastolic' => ['nullable', 'required_with:systolic', 'integer', 'between:30,180', 'lt:systolic'],
            'fundal_height' => ['nullable', 'integer', 'between:8,50'],
            'presentation' => ['nullable', Rule::in($m['presentations'])],
            'fetal_heart_rate' => ['nullable', 'integer', 'between:60,220'],
            'fetal_movement' => ['nullable', 'boolean'],
            'urine_protein' => ['nullable', Rule::in($m['urine_protein'])],
            'oedema' => ['nullable', Rule::in($m['oedema'])],
            'haemoglobin' => ['nullable', 'numeric', 'between:2,20'],
            'interventions' => ['array'],
            'interventions.*' => [Rule::in(array_keys($m['interventions']))],
            'complaints' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'next_visit' => ['nullable', 'date', 'after:today'],
        ]);

        $visit = $this->maternity->recordAnc($pregnancy, $data, $request->user());
        $visit->setRelation('pregnancy', $pregnancy);
        $alerts = $visit->alerts();

        return redirect()->to(route('maternity.show', $pregnancy).'#anc')
            ->with($alerts ? 'warning' : 'success', $alerts ? 'ANC visit saved. DANGER SIGNS: '.implode('; ', $alerts).'.' : 'ANC visit saved.');
    }

    public function partograph(Pregnancy $pregnancy): View
    {
        $pregnancy->load(['patient', 'partograph.recorder']);

        return view('maternity.partograph', [
            'pregnancy' => $pregnancy,
            'patient' => $pregnancy->patient,
            'visit' => null,
            'charts' => $this->partographCharts($pregnancy),
        ]);
    }

    public function startLabour(Pregnancy $pregnancy): RedirectResponse
    {
        $this->maternity->startLabour($pregnancy);

        return redirect()->route('maternity.partograph', $pregnancy)->with('success', 'Labour started — partograph opened.');
    }

    public function storePartograph(Request $request, Pregnancy $pregnancy): RedirectResponse
    {
        $m = config('emr.maternity');
        $data = $request->validate([
            'cervical_dilation' => ['nullable', 'numeric', 'between:0,10'],
            'descent' => ['nullable', 'integer', 'between:0,5'],
            'contractions' => ['nullable', 'integer', 'between:0,10'],
            'contraction_strength' => ['nullable', Rule::in(['<20s', '20-40s', '>40s'])],
            'fetal_heart_rate' => ['nullable', 'integer', 'between:50,220'],
            'liquor' => ['nullable', Rule::in(array_keys($m['liquor']))],
            'moulding' => ['nullable', Rule::in($m['moulding'])],
            'pulse' => ['nullable', 'integer', 'between:30,220'],
            'systolic' => ['nullable', 'integer', 'between:60,260'],
            'diastolic' => ['nullable', 'integer', 'between:30,180'],
            'temperature' => ['nullable', 'numeric', 'between:34,43'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $entry = $this->maternity->addPartograph($pregnancy, $data, $request->user());
        $alerts = $entry->alerts();

        return back()->with($alerts ? 'warning' : 'success', $alerts ? 'Recorded. Attention: '.implode(', ', $alerts).'.' : 'Observation recorded.');
    }

    public function deliveryForm(Pregnancy $pregnancy): View
    {
        return view('maternity.delivery', ['pregnancy' => $pregnancy->load('patient'), 'patient' => $pregnancy->patient, 'visit' => null]);
    }

    public function storeDelivery(Request $request, Pregnancy $pregnancy): RedirectResponse
    {
        $m = config('emr.maternity');
        $data = $request->validate([
            'delivered_at' => ['required', 'date', 'before_or_equal:now'],
            'mode' => ['required', Rule::in(array_keys($m['delivery_modes']))],
            'blood_loss_ml' => ['nullable', 'integer', 'between:0,10000'],
            'placenta_complete' => ['boolean'],
            'perineum' => ['nullable', Rule::in(array_keys($m['perineum']))],
            'complications' => ['array'],
            'complications.*' => [Rule::in(array_keys($m['complications']))],
            'maternal_outcome' => ['required', Rule::in(['alive', 'died'])],
            'notes' => ['nullable', 'string', 'max:3000'],
            'babies' => ['required', 'array', 'min:1', 'max:6'],
            'babies.*.sex' => ['required', Rule::in(['male', 'female'])],
            'babies.*.outcome' => ['required', Rule::in(array_keys($m['baby_outcomes']))],
            'babies.*.birth_weight_g' => ['nullable', 'integer', 'between:300,7000'],
            'babies.*.apgar_1' => ['nullable', 'integer', 'between:0,10'],
            'babies.*.apgar_5' => ['nullable', 'integer', 'between:0,10'],
            'babies.*.resuscitated' => ['boolean'],
            'babies.*.name' => ['nullable', 'string', 'max:100'],
        ]);

        $data['gestation_weeks'] = intdiv(max(0, $pregnancy->gestationDays(Carbon::parse($data['delivered_at']))), 7);
        // PPH is defined by blood loss ≥ 500 ml; flag it automatically.
        if (($data['blood_loss_ml'] ?? 0) >= 500 && ! in_array('pph', $data['complications'] ?? [], true)) {
            $data['complications'][] = 'pph';
        }

        $babies = $data['babies'];
        unset($data['babies']);
        $delivery = $this->maternity->recordDelivery($pregnancy, $data, $babies, $request->user());

        return redirect()->to(route('maternity.show', $pregnancy).'#delivery')
            ->with('success', 'Delivery recorded. '.$delivery->babies()->whereNotNull('patient_id')->count().' baby record(s) created.');
    }

    public function storePostnatal(Request $request, Pregnancy $pregnancy): RedirectResponse
    {
        $data = $request->validateWithBag('pnc', [
            'visit_date' => ['required', 'date', 'before_or_equal:today'],
            'systolic' => ['nullable', 'integer', 'between:60,260'],
            'diastolic' => ['nullable', 'integer', 'between:30,180'],
            'temperature' => ['nullable', 'numeric', 'between:34,43'],
            'uterus' => ['nullable', 'string', 'max:30'],
            'lochia' => ['nullable', 'string', 'max:30'],
            'breastfeeding' => ['nullable', 'string', 'max:30'],
            'wound' => ['nullable', 'string', 'max:30'],
            'mood_concern' => ['boolean'],
            'family_planning' => ['nullable', 'string', 'max:255'],
            'baby_weight_g' => ['nullable', 'integer', 'between:300,9000'],
            'cord' => ['nullable', 'string', 'max:30'],
            'jaundice' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->maternity->recordPostnatal($pregnancy, $data, $request->user());

        return redirect()->to(route('maternity.show', $pregnancy).'#postnatal')->with('success', 'Postnatal visit saved.');
    }

    public function end(Request $request, Pregnancy $pregnancy): RedirectResponse
    {
        $data = $request->validate(['end_reason' => ['required', 'string', 'max:255']]);
        $this->maternity->end($pregnancy, $data['end_reason']);

        return redirect()->route('maternity.show', $pregnancy)->with('success', 'Pregnancy record closed.');
    }

    /**
     * Partograph: cervical dilation against hours since the active phase,
     * with WHO alert (1 cm/h from first ≥4 cm) and action (alert + 4 h) lines.
     */
    protected function partographCharts(Pregnancy $pregnancy): array
    {
        $entries = $pregnancy->partograph;
        $start = $pregnancy->labour_started_at ?? $entries->first()?->recorded_at;
        if (! $start || $entries->isEmpty()) {
            return [];
        }

        $hours = fn ($e) => round($start->diffInMinutes($e->recorded_at) / 60, 2);
        $dilation = $entries->whereNotNull('cervical_dilation')->map(fn ($e) => ['x' => $hours($e), 'y' => $e->cervical_dilation])->values();
        $fhr = $entries->whereNotNull('fetal_heart_rate')->map(fn ($e) => ['x' => $hours($e), 'y' => $e->fetal_heart_rate])->values();

        $charts = [];
        if ($dilation->isNotEmpty()) {
            $active = $dilation->first(fn ($p) => $p['y'] >= 4);
            $reference = [];
            if ($active) {
                $toFull = 10 - $active['y'];
                $reference = [
                    ['label' => 'Alert line', 'data' => [['x' => $active['x'], 'y' => $active['y']], ['x' => $active['x'] + $toFull, 'y' => 10]], 'dash' => [6, 4]],
                    ['label' => 'Action line (+4 h)', 'data' => [['x' => $active['x'] + 4, 'y' => $active['y']], ['x' => $active['x'] + 4 + $toFull, 'y' => 10]], 'dash' => []],
                ];
            }
            $charts[] = ['type' => 'partograph', 'title' => 'Cervical dilation', 'unit' => 'cm', 'min' => 0, 'max' => 10,
                'series' => [['label' => 'Dilation', 'data' => $dilation]], 'reference' => $reference];
        }
        if ($fhr->isNotEmpty()) {
            $charts[] = ['type' => 'partograph', 'title' => 'Fetal heart rate (normal 110–160)', 'unit' => '/min', 'min' => 80, 'max' => 200,
                'series' => [['label' => 'FHR', 'data' => $fhr]], 'reference' => [
                    ['label' => '110', 'data' => [['x' => 0, 'y' => 110], ['x' => max(1, $fhr->max('x')), 'y' => 110]], 'dash' => [4, 4]],
                    ['label' => '160', 'data' => [['x' => 0, 'y' => 160], ['x' => max(1, $fhr->max('x')), 'y' => 160]], 'dash' => [4, 4]],
                ]];
        }

        return $charts;
    }
}
