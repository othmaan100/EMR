<?php

namespace App\Http\Controllers;

use App\Models\Diagnosis;
use App\Models\ImagingAttachment;
use App\Models\ImagingOrder;
use App\Integrations\Pacs\PacsService;
use App\Services\RadiologyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RadiologyController extends Controller
{
    public const TABS = [
        'perform' => ['label' => 'To perform', 'statuses' => ['requested', 'scheduled'], 'icon' => 'bi-camera'],
        'report' => ['label' => 'To report', 'statuses' => ['performed'], 'icon' => 'bi-pencil-square'],
        'done' => ['label' => 'Reported today', 'statuses' => ['completed'], 'icon' => 'bi-patch-check'],
    ];

    public function __construct(protected RadiologyService $radiology) {}

    public function index(Request $request): View
    {
        $tab = array_key_exists($request->query('tab'), self::TABS) ? $request->query('tab') : 'perform';
        $q = trim((string) $request->query('q'));

        $scope = fn ($query, string $key) => $query->whereIn('status', self::TABS[$key]['statuses'])
            ->when($key === 'done', fn ($q) => $q->whereDate('completed_at', today()));

        $orders = ImagingOrder::with(['patient', 'test', 'orderedBy'])
            ->tap(fn ($query) => $scope($query, $tab))
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                ->where('order_number', $q)
                ->orWhereHas('patient', fn ($p) => $p->search($q))))
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 0 ELSE 1 END")
            ->orderByRaw('COALESCE(scheduled_for, created_at) '.($tab === 'done' ? 'desc' : 'asc'))
            ->paginate(30)->withQueryString();

        $counts = collect(self::TABS)->map(fn ($t, $key) => ImagingOrder::query()->tap(fn ($query) => $scope($query, $key))->count());

        return view('radiology.index', compact('orders', 'tab', 'counts', 'q'));
    }

    public function show(ImagingOrder $imagingOrder): View
    {
        $imagingOrder->load(['patient', 'test', 'orderedBy', 'performer', 'reporter', 'attachments.uploader', 'visit.clinic']);

        return view('radiology.show', [
            'order' => $imagingOrder,
            'patient' => $imagingOrder->patient,
            'visit' => $imagingOrder->visit,
            'viewerUrl' => app(PacsService::class)->viewerUrl($imagingOrder),
            'pacsEnabled' => app(PacsService::class)->enabled(),
            'previous' => ImagingOrder::where('patient_id', $imagingOrder->patient_id)->whereKeyNot($imagingOrder->id)
                ->where('status', 'completed')->with('test')->latest('completed_at')->limit(5)->get(),
        ]);
    }

    /**
     * Look for this order's study in the PACS now (the sync also runs every 5 minutes).
     */
    public function linkPacs(Request $request, ImagingOrder $imagingOrder, PacsService $pacs): RedirectResponse
    {
        abort_unless($pacs->enabled(), 404);

        try {
            $found = $pacs->link($imagingOrder, $request->user());
        } catch (\Throwable $e) {
            return back()->with('error', 'Could not reach the PACS: '.$e->getMessage());
        }

        return back()->with($found ? 'success' : 'warning', $found
            ? 'Images found in the PACS and linked.'
            : "No study with accession number {$imagingOrder->order_number} yet. Check it was entered at the modality.");
    }

    public function schedule(Request $request, ImagingOrder $imagingOrder): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date', 'after_or_equal:today'],
            'time' => ['required', 'date_format:H:i'],
        ]);
        $this->radiology->schedule($imagingOrder, Carbon::parse($data['date'].' '.$data['time']));

        return back()->with('success', "{$imagingOrder->order_number} scheduled for ".format_date($imagingOrder->scheduled_for, true).'.');
    }

    public function perform(Request $request, ImagingOrder $imagingOrder): RedirectResponse
    {
        $this->radiology->perform($imagingOrder, $request->user());

        return redirect()->route('radiology.show', $imagingOrder)->with('success', 'Examination marked as performed. Upload images, then report.');
    }

    public function saveReport(Request $request, ImagingOrder $imagingOrder): RedirectResponse
    {
        $data = $request->validate([
            'technique' => ['nullable', 'string', 'max:2000'],
            'findings' => ['nullable', 'string', 'max:10000'],
            'impression' => ['nullable', 'string', 'max:3000'],
            'sign' => ['boolean'],
        ]);

        $this->radiology->saveReport($imagingOrder, $request->user(), $data);

        if ($request->boolean('sign')) {
            $this->radiology->sign($imagingOrder, $request->user());

            return redirect()->route('radiology.index', ['tab' => 'report'])
                ->with('success', "Report for {$imagingOrder->order_number} signed and released.");
        }

        return back()->with('success', 'Draft report saved.');
    }

    public function upload(Request $request, ImagingOrder $imagingOrder): RedirectResponse
    {
        $request->validate([
            'files' => ['required', 'array', 'max:20'],
            'files.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:20480'],
            'caption' => ['nullable', 'string', 'max:255'],
        ], ['files.*.mimes' => 'Only JPEG, PNG, WEBP or PDF files can be attached.']);

        foreach ($request->file('files') as $file) {
            $this->radiology->attach($imagingOrder, $file, $request->user(), $request->input('caption'));
        }

        return back()->with('success', count($request->file('files')).' file(s) attached.');
    }

    public function detach(ImagingAttachment $attachment): RedirectResponse
    {
        $this->radiology->detach($attachment);

        return back()->with('success', 'File removed.');
    }

    /**
     * Stream an attachment. Radiology staff always; clinicians once released.
     */
    public function attachment(Request $request, ImagingAttachment $attachment): StreamedResponse
    {
        $this->authorizeView($request, $attachment->order);
        abort_unless(Storage::disk(ImagingAttachment::DISK)->exists($attachment->path), 404);

        return Storage::disk(ImagingAttachment::DISK)->response(
            $attachment->path,
            $attachment->original_name,
            ['Cache-Control' => 'private, max-age=3600'],
            'inline'
        );
    }

    public function report(Request $request, ImagingOrder $imagingOrder): View
    {
        $this->authorizeView($request, $imagingOrder);

        return view('radiology.report', [
            'order' => $imagingOrder->load(['patient', 'test', 'orderedBy', 'performer', 'reporter', 'attachments', 'visit.clinic']),
            'diagnoses' => $imagingOrder->consultation_id
                ? Diagnosis::where('consultation_id', $imagingOrder->consultation_id)->orderByDesc('is_primary')->get()
                : collect(),
        ]);
    }

    protected function authorizeView(Request $request, ImagingOrder $order): void
    {
        $user = $request->user();
        abort_unless($user->can('radiology.process') || ($user->can('imaging.results.view') && $order->isReleased()), 403,
            'This imaging report has not been released yet.');
    }
}
