<?php

namespace App\Http\Controllers;

use App\Models\Admission;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Drug;
use App\Models\ImagingOrder;
use App\Models\LabOrder;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\StockBatch;
use App\Models\Visit;
use App\Support\SystemHealth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        return view('dashboard', [
            'stats' => [
                ['label' => 'Registered Patients', 'value' => number_format(Patient::count()), 'icon' => 'bi-person-vcard', 'color' => 'info'],
                ['label' => 'New Patients Today', 'value' => Patient::whereDate('created_at', today())->count(), 'icon' => 'bi-person-plus', 'color' => 'success'],
                ['label' => "Today's Appointments", 'value' => Appointment::whereDate('scheduled_at', today())->whereIn('status', ['scheduled', 'checked_in', 'completed'])->count(), 'icon' => 'bi-calendar-check', 'color' => 'warning'],
                ['label' => 'Patients in Queue', 'value' => Visit::open()->count(), 'icon' => 'bi-people-fill', 'color' => 'primary',
                    'note' => Visit::whereDate('checked_in_at', today())->count().' visits today'],
            ],
            'worklists' => $this->worklists($user),
            // Cached briefly: the checks touch the disk and migrations table.
            'healthIssues' => $user->can('system.manage')
                ? Cache::remember('emr.health.failures', 300, fn () => collect(app(SystemHealth::class)->checks())->where('status', 'fail')->count())
                : 0,
            'recentActivity' => $user->can('audit.view')
                ? AuditLog::with('user')->latest('id')->limit(8)->get()
                : collect(),
        ]);
    }

    /**
     * "Waiting for you" counts for each department the user works in.
     */
    protected function worklists($user): array
    {
        $expiring = fn () => StockBatch::where('quantity_on_hand', '>', 0)->whereDate('expiry_date', '<=', today()->addDays(Drug::EXPIRY_WARNING_DAYS))->count();

        return array_values(array_filter([
            $user->can('vitals.record') ? ['Awaiting triage', Visit::where('status', Visit::WAITING_TRIAGE)->count(), route('vitals.worklist'), 'bi-heart-pulse', 'warning'] : null,
            $user->can('consultations.create') ? ['Waiting for a doctor', Visit::where('status', Visit::WAITING_DOCTOR)->count(), route('queue.index', ['mine' => 1]), 'bi-clipboard2-pulse', 'info'] : null,
            $user->can('admissions.view') ? ['Inpatients admitted', Admission::current()->count(), route('inpatients.index'), 'bi-hospital', 'primary'] : null,
            $user->can('theatre.view') ? ['Operations today', \App\Models\Surgery::active()->whereDate('scheduled_at', today())->count(), route('theatre.index'), 'bi-scissors', 'danger'] : null,
            $user->can('lab.process') ? ['Lab samples to collect', LabOrder::where('status', 'requested')->count(), route('lab.index'), 'bi-droplet', 'primary'] : null,
            $user->can('lab.verify') ? ['Lab results to verify', LabOrder::where('status', 'in_progress')->count(), route('lab.index', ['tab' => 'verify']), 'bi-patch-question', 'warning'] : null,
            $user->can('radiology.process') ? ['Imaging to perform', ImagingOrder::whereIn('status', ['requested', 'scheduled'])->count(), route('radiology.index'), 'bi-camera', 'primary'] : null,
            $user->can('radiology.report') ? ['Imaging to report', ImagingOrder::where('status', 'performed')->count(), route('radiology.index', ['tab' => 'report']), 'bi-pencil-square', 'warning'] : null,
            $user->can('pharmacy.dispense') ? ['Prescriptions to dispense', Prescription::whereIn('status', ['pending', 'partially_dispensed'])->count(), route('pharmacy.index'), 'bi-capsule', 'primary'] : null,
            $user->can('inventory.view') ? ['Drugs at/below reorder level', Drug::lowStock()->count(), route('inventory.index', ['filter' => 'low']), 'bi-arrow-down-circle', 'danger'] : null,
            $user->can('inventory.view') ? ['Batches expiring soon', $expiring(), route('inventory.index', ['filter' => 'expiring']), 'bi-calendar-x', 'warning'] : null,
            $user->can('stores.manage') ? ['Requisitions to issue', \App\Models\Requisition::whereIn('status', \App\Models\Requisition::OPEN)->count(), route('requisitions.index', ['tab' => 'open']), 'bi-clipboard-check', 'warning'] : null,
            $user->can('stores.manage') ? ['Store items at/below reorder level', \App\Models\StoreItem::lowStock()->count(), route('stores.index', ['filter' => 'low']), 'bi-boxes', 'danger'] : null,
            $user->can('purchasing.approve') ? ['Purchase orders to approve', \App\Models\PurchaseOrder::where('status', 'draft')->count(), route('purchasing.index', ['status' => 'draft']), 'bi-cart-check', 'info'] : null,
            $user->can('payables.manage') ? ['Supplier invoices overdue', \App\Models\SupplierInvoice::where('status', 'unpaid')->whereDate('due_date', '<', today())->count(), route('invoices.index', ['tab' => 'overdue']), 'bi-journal-text', 'danger'] : null,
            $user->can('appointments.manage') ? ['Online appointment requests', \App\Models\Appointment::where('status', 'requested')->count(), route('appointments.requests'), 'bi-globe', 'warning'] : null,
            $user->can('claims.preauth') ? ['PA requests awaiting HMO', \App\Models\Preauthorization::where('status', 'requested')->count(), route('preauth.index'), 'bi-shield-check', 'warning'] : null,
            $user->can('billing.claims') ? ['Insurance bills to batch', \App\Models\Bill::where('claim_status', 'pending')->whereNull('claim_batch_id')->count(), route('billing.claims'), 'bi-file-earmark-medical', 'info'] : null,
        ]));
    }
}
