<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Integrations\Payments\OnlinePaymentService;
use App\Models\Appointment;
use App\Models\Bill;
use App\Models\Clinic;
use App\Models\Diagnosis;
use App\Models\ImagingOrder;
use App\Models\LabOrder;
use App\Models\Patient;
use App\Models\PatientAccount;
use App\Models\Payment;
use App\Services\ImmunizationService;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * What a signed-in patient can see and do. Every record is checked against
 * the "current" patient (the account holder, or one of their children).
 */
class PortalController extends Controller
{
    public function dashboard(Request $request): View
    {
        $patient = $this->subject($request);

        return $this->page($request, 'portal.dashboard', [
            'appointments' => $patient->appointments()->with('clinic')->whereIn('status', ['scheduled', 'requested'])
                ->where('scheduled_at', '>=', today())->orderBy('scheduled_at')->limit(3)->get(),
            'labResults' => $patient->labOrders()->where('status', 'completed')->latest('completed_at')->limit(3)->get(),
            'imagingResults' => $patient->imagingOrders()->with('test')->where('status', 'completed')->latest('completed_at')->limit(3)->get(),
            'balance' => $patient->outstandingBalance(),
            'medications' => $patient->prescriptions()->with('items')->latest('id')->limit(2)->get(),
        ]);
    }

    // ------------------------------------------------------------------ appointments

    public function appointments(Request $request): View
    {
        $patient = $this->subject($request);

        return $this->page($request, 'portal.appointments', [
            'upcoming' => $patient->appointments()->with(['clinic', 'doctor'])->whereIn('status', ['scheduled', 'requested'])
                ->where('scheduled_at', '>=', today())->orderBy('scheduled_at')->get(),
            'past' => $patient->appointments()->with('clinic')->where(fn ($q) => $q->whereNotIn('status', ['scheduled', 'requested'])
                ->orWhere('scheduled_at', '<', today()))->latest('scheduled_at')->limit(20)->get(),
            'clinics' => Clinic::active()->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function requestAppointment(Request $request): RedirectResponse
    {
        $patient = $this->subject($request);
        $data = $request->validate([
            'clinic_id' => ['required', 'exists:clinics,id'],
            'date' => ['required', 'date', 'after:today', 'before_or_equal:'.today()->addDays(90)->toDateString()],
            'time_of_day' => ['required', 'in:morning,afternoon'],
            'reason' => ['required', 'string', 'max:255'],
        ]);
        abort_unless(Clinic::active()->whereKey($data['clinic_id'])->exists(), 422);

        $pending = $patient->appointments()->where('status', 'requested')->count();
        if ($pending >= 3) {
            return back()->withErrors(['clinic_id' => 'You already have 3 requests waiting for the hospital to confirm.'])->withInput();
        }

        $appointment = new Appointment([
            'patient_id' => $patient->id,
            'clinic_id' => $data['clinic_id'],
            'scheduled_at' => $data['date'].($data['time_of_day'] === 'morning' ? ' 09:00' : ' 14:00'),
            'type' => 'new',
            'reason' => $data['reason'],
            'notes' => 'Requested online — prefers the '.$data['time_of_day'].'.',
        ]);
        $appointment->status = 'requested';
        $appointment->source = 'portal';
        $appointment->save();

        return back()->with('success', 'Request sent. The hospital will confirm the date and time.');
    }

    public function cancelAppointment(Request $request, Appointment $appointment): RedirectResponse
    {
        $this->owns($request, $appointment->patient_id);
        abort_unless(in_array($appointment->status, ['scheduled', 'requested'], true) && $appointment->scheduled_at->gte(today()), 422);

        $appointment->forceFill(['status' => 'cancelled', 'cancel_reason' => 'Cancelled by patient online'])->save();

        return back()->with('success', 'Appointment cancelled.');
    }

    // ------------------------------------------------------------------ results

    public function results(Request $request): View
    {
        $patient = $this->subject($request);

        return $this->page($request, 'portal.results', [
            'labOrders' => $patient->labOrders()->with('items.test')->where('status', 'completed')->latest('completed_at')->paginate(15, ['*'], 'lab'),
            'imagingOrders' => $patient->imagingOrders()->with('test')->where('status', 'completed')->latest('completed_at')->get(),
        ]);
    }

    public function labResult(Request $request, LabOrder $labOrder): View
    {
        $this->owns($request, $labOrder->patient_id);
        abort_unless($labOrder->isReleased(), 404);
        Audit::log('portal_result_viewed', "Patient viewed lab results {$labOrder->order_number} online", $labOrder);

        return view('laboratory.report', [
            'order' => $labOrder->load(['patient', 'items.test', 'items.results', 'items.enteredBy', 'items.verifiedBy', 'orderedBy', 'collectedBy', 'visit.clinic']),
            'diagnoses' => $labOrder->consultation_id ? Diagnosis::where('consultation_id', $labOrder->consultation_id)->orderByDesc('is_primary')->get() : collect(),
            'portal' => true,
        ]);
    }

    public function imagingResult(Request $request, ImagingOrder $imagingOrder): View
    {
        $this->owns($request, $imagingOrder->patient_id);
        abort_unless($imagingOrder->isReleased(), 404);
        Audit::log('portal_result_viewed', "Patient viewed imaging report {$imagingOrder->order_number} online", $imagingOrder);

        return view('radiology.report', [
            'order' => $imagingOrder->load(['patient', 'test', 'orderedBy', 'performer', 'reporter', 'attachments', 'visit.clinic']),
            'diagnoses' => $imagingOrder->consultation_id ? Diagnosis::where('consultation_id', $imagingOrder->consultation_id)->orderByDesc('is_primary')->get() : collect(),
            'portal' => true,
        ]);
    }

    // ------------------------------------------------------------------ bills

    public function bills(Request $request): View
    {
        $patient = $this->subject($request);

        return $this->page($request, 'portal.bills', [
            'bills' => $patient->bills()->with(['items', 'visit.clinic'])->latest('id')->paginate(15),
            'payments' => Payment::where('patient_id', $patient->id)->latest('id')->limit(20)->get(),
            'balance' => $patient->outstandingBalance(),
            'canPayOnline' => setting('portal_online_payments') && app(OnlinePaymentService::class)->enabled(),
        ]);
    }

    /**
     * Pay all of the current patient's unpaid items online.
     */
    public function payOnline(Request $request, OnlinePaymentService $payments): RedirectResponse
    {
        abort_unless(setting('portal_online_payments') && $payments->enabled(), 404);
        $payment = $payments->start($this->subject($request), [], 'portal');

        return redirect()->away($payment->checkout_url);
    }

    public function invoice(Request $request, Bill $bill): View
    {
        $this->owns($request, $bill->patient_id);

        return view('billing.invoice', ['bill' => $bill->load(['patient', 'items', 'visit.clinic', 'insuranceProvider'])]);
    }

    public function receipt(Request $request, Payment $payment): View
    {
        $this->owns($request, $payment->patient_id);

        return view('billing.receipt', ['payment' => $payment->load(['patient', 'receiver', 'allocations.item.bill'])]);
    }

    // ------------------------------------------------------------------ immunizations & profile

    public function immunizations(Request $request, ImmunizationService $immunizations): View
    {
        $patient = $this->subject($request);

        return $this->page($request, 'portal.immunizations', [
            'schedule' => $immunizations->schedule($patient->load('immunizations')),
        ]);
    }

    public function profile(Request $request): View
    {
        return $this->page($request, 'portal.profile', []);
    }

    public function password(Request $request): RedirectResponse
    {
        /** @var PatientAccount $account */
        $account = $request->user('patient');
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        if (! Hash::check($data['current_password'], $account->password)) {
            return back()->withErrors(['current_password' => 'Your current password is incorrect.']);
        }

        $account->forceFill(['password' => $data['password']])->save();
        Audit::log('portal_password_changed', 'Patient changed their portal password', $account->patient);

        return back()->with('success', 'Password changed.');
    }

    /**
     * Switch between the account holder and their children.
     */
    public function switchPatient(Request $request, Patient $patient): RedirectResponse
    {
        $this->owns($request, $patient->id);
        $request->session()->put('portal_subject', $patient->id);

        return redirect()->route('portal.dashboard');
    }

    // ------------------------------------------------------------------ helpers

    protected function subject(Request $request): Patient
    {
        /** @var PatientAccount $account */
        $account = $request->user('patient');
        $id = (int) $request->session()->get('portal_subject', $account->patient_id);

        if (! in_array($id, $account->patientIds(), true)) {
            $request->session()->forget('portal_subject');
            $id = $account->patient_id;
        }

        return Patient::findOrFail($id);
    }

    protected function owns(Request $request, int $patientId): void
    {
        abort_unless(in_array($patientId, $request->user('patient')->patientIds(), true), 404);
    }

    protected function page(Request $request, string $view, array $data): View
    {
        $account = $request->user('patient');

        return view($view, $data + [
            'account' => $account,
            'patient' => $this->subject($request),
            'family' => Patient::whereIn('id', $account->patientIds())->orderBy('id')->get(),
        ]);
    }
}
