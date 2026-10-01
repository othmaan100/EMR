<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\CatalogController;
use App\Http\Controllers\Admin\ClinicController;
use App\Http\Controllers\Admin\LabParameterController;
use App\Http\Controllers\Admin\ImportController;
use App\Http\Controllers\Admin\IntegrationController;
use App\Http\Controllers\Admin\SmsController;
use App\Http\Controllers\Admin\SystemController;
use App\Http\Controllers\Admin\WardController;
use App\Http\Controllers\InpatientController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\ImmunizationController;
use App\Http\Controllers\LaboratoryController;
use App\Http\Controllers\MaternityController;
use App\Http\Controllers\PharmacyController;
use App\Http\Controllers\PreauthorizationController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\TheatreController;
use App\Http\Controllers\RadiologyController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SpecialtyController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\RequisitionController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\SupplierInvoiceController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\ClaimController;
use App\Http\Controllers\PriceListController;
use App\Http\Controllers\ConsultationController;
use App\Http\Controllers\NursingNoteController;
use App\Http\Controllers\VisitController;
use App\Http\Controllers\VitalSignController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\InsuranceProviderController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\PortalAccessController;
use App\Http\Controllers\SetupController;
use Illuminate\Support\Facades\Route;

// First-run setup wizard (locked by EnsureInstalled after completion).
Route::prefix('setup')->name('setup.')->controller(SetupController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('{step}', 'show')->name('step');
    Route::post('{step}', 'store')->name('store');
});

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->middleware('throttle:20,1');
});

Route::middleware('auth:web')->group(function () {
    Route::redirect('/', '/dashboard');
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');
    Route::post('session/ping', [LoginController::class, 'ping'])->name('session.ping');

    // Own account
    Route::get('account/profile', [AccountController::class, 'edit'])->name('account.profile');
    Route::put('account/profile', [AccountController::class, 'update'])->name('account.profile.update');
    Route::put('account/password', [AccountController::class, 'updatePassword'])->name('account.password.update');
    Route::get('account/change-password', [AccountController::class, 'forcePassword'])->name('password.force');

    // Patients
    Route::post('patients/nin-lookup', [PatientController::class, 'ninLookup'])->name('patients.nin')
        ->middleware(['permission:patients.create|patients.update', 'throttle:20,1']);
    Route::middleware('permission:patients.create')->group(function () {
        Route::get('patients/create', [PatientController::class, 'create'])->name('patients.create');
        Route::post('patients', [PatientController::class, 'store'])->name('patients.store');
    });
    Route::middleware('permission:patients.update')->group(function () {
        Route::get('patients/{patient}/edit', [PatientController::class, 'edit'])->name('patients.edit');
        Route::put('patients/{patient}', [PatientController::class, 'update'])->name('patients.update');
    });
    Route::delete('patients/{patient}', [PatientController::class, 'destroy'])->name('patients.destroy')
        ->middleware('permission:patients.delete');
    Route::middleware('permission:portal.manage')->controller(PortalAccessController::class)->group(function () {
        Route::post('patients/{patient}/portal', 'store')->name('patients.portal.store');
        Route::get('patients/{patient}/portal/letter', 'letter')->name('patients.portal.letter');
        Route::delete('patients/{patient}/portal', 'destroy')->name('patients.portal.destroy');
    });
    Route::middleware('permission:patients.view')->group(function () {
        Route::get('patients/lookup', [PatientController::class, 'lookup'])->name('patients.lookup');
        Route::get('patients', [PatientController::class, 'index'])->name('patients.index');
        Route::get('patients/{patient}', [PatientController::class, 'show'])->name('patients.show');
        Route::get('patients/{patient}/photo', [PatientController::class, 'photo'])->name('patients.photo');
        Route::get('patients/{patient}/card', [PatientController::class, 'card'])->name('patients.card');
    });

    // Check-in & queue
    Route::middleware('permission:visits.checkin')->group(function () {
        Route::get('patients/{patient}/check-in', [VisitController::class, 'create'])->name('visits.create');
        Route::post('patients/{patient}/check-in', [VisitController::class, 'store'])->name('visits.store');
        Route::post('appointments/{appointment}/check-in', [AppointmentController::class, 'checkIn'])->name('appointments.check-in');
    });
    Route::get('queue', [VisitController::class, 'queue'])->name('queue.index')->middleware('permission:queue.view');
    Route::patch('visits/{visit}/status', [VisitController::class, 'move'])->name('visits.move')->middleware('permission:queue.manage');

    // Nursing / triage
    Route::middleware('permission:vitals.record')->group(function () {
        Route::get('triage', [VitalSignController::class, 'worklist'])->name('vitals.worklist');
        Route::get('visits/{visit}/triage', [VitalSignController::class, 'triage'])->name('vitals.triage');
        Route::post('visits/{visit}/triage', [VitalSignController::class, 'storeTriage'])->name('vitals.triage.store');
        Route::get('patients/{patient}/vitals/create', [VitalSignController::class, 'create'])->name('vitals.create');
        Route::post('patients/{patient}/vitals', [VitalSignController::class, 'store'])->name('vitals.store');
        Route::patch('vitals/{vital}/void', [VitalSignController::class, 'void'])->name('vitals.void');
    });
    Route::get('patients/{patient}/vitals', [VitalSignController::class, 'index'])->name('vitals.index')
        ->middleware('permission:vitals.view');
    Route::post('patients/{patient}/nursing-notes', [NursingNoteController::class, 'store'])->name('nursing-notes.store')
        ->middleware('permission:nursing_notes.create');

    // Consultation (edit rights are enforced per-consultation in the controller)
    Route::middleware('permission:consultations.view')->group(function () {
        Route::get('visits/{visit}/consultation', [ConsultationController::class, 'show'])->name('consultations.show');
        Route::get('icd10/search', [ConsultationController::class, 'icd10'])->name('icd10.search');
        Route::get('prescriptions/{prescription}/print', [ConsultationController::class, 'printPrescription'])->name('prescriptions.print');
    });
    Route::middleware('permission:consultations.create')->controller(ConsultationController::class)->group(function () {
        Route::put('consultations/{consultation}', 'update')->name('consultations.update');
        Route::post('consultations/{consultation}/diagnoses', 'addDiagnosis')->name('consultations.diagnoses.store');
        Route::delete('diagnoses/{diagnosis}', 'removeDiagnosis')->name('diagnoses.destroy');
        Route::post('consultations/{consultation}/lab-orders', 'orderLab')->name('consultations.lab.store');
        Route::post('consultations/{consultation}/imaging-orders', 'orderImaging')->name('consultations.imaging.store');
        Route::patch('orders/{type}/{id}/cancel', 'cancelOrder')->name('orders.cancel')->whereIn('type', ['lab', 'imaging'])->whereNumber('id');
        Route::post('consultations/{consultation}/prescription-items', 'prescribe')->name('consultations.prescribe');
        Route::delete('prescription-items/{item}', 'removePrescriptionItem')->name('prescription-items.destroy');
        Route::post('consultations/{consultation}/sign', 'sign')->name('consultations.sign');
        Route::post('consultations/{consultation}/addenda', 'addAddendum')->name('consultations.addenda.store');
    });

    // Laboratory
    Route::middleware('permission:lab.process')->controller(LaboratoryController::class)->group(function () {
        Route::get('lab', 'index')->name('lab.index');
        Route::get('lab/orders/{labOrder}', 'show')->name('lab.show');
        Route::post('lab/orders/{labOrder}/collect', 'collect')->name('lab.collect');
        Route::post('lab/orders/{labOrder}/reject', 'reject')->name('lab.reject');
        Route::post('lab/orders/{labOrder}/results', 'saveResults')->name('lab.results');
        Route::get('lab/orders/{labOrder}/label', 'label')->name('lab.label');
    });
    Route::post('lab/orders/{labOrder}/verify', [LaboratoryController::class, 'verify'])->name('lab.verify')
        ->middleware('permission:lab.verify');
    Route::get('lab/orders/{labOrder}/report', [LaboratoryController::class, 'report'])->name('lab.report')
        ->middleware('permission:lab.process|lab.results.view');

    // Radiology
    Route::middleware('permission:radiology.process')->controller(RadiologyController::class)->group(function () {
        Route::get('radiology', 'index')->name('radiology.index');
        Route::get('radiology/orders/{imagingOrder}', 'show')->name('radiology.show');
        Route::post('radiology/orders/{imagingOrder}/schedule', 'schedule')->name('radiology.schedule');
        Route::post('radiology/orders/{imagingOrder}/perform', 'perform')->name('radiology.perform');
        Route::post('radiology/orders/{imagingOrder}/attachments', 'upload')->name('radiology.upload');
        Route::delete('radiology/attachments/{attachment}', 'detach')->name('radiology.detach');
        Route::post('radiology/orders/{imagingOrder}/pacs', 'linkPacs')->name('radiology.pacs');
    });
    Route::post('radiology/orders/{imagingOrder}/report', [RadiologyController::class, 'saveReport'])->name('radiology.report.save')
        ->middleware('permission:radiology.report');
    Route::middleware('permission:radiology.process|imaging.results.view')->group(function () {
        Route::get('radiology/orders/{imagingOrder}/report', [RadiologyController::class, 'report'])->name('radiology.report');
        Route::get('radiology/attachments/{attachment}', [RadiologyController::class, 'attachment'])->name('radiology.attachment');
    });

    // Pharmacy & inventory
    Route::middleware('permission:pharmacy.dispense')->controller(PharmacyController::class)->group(function () {
        Route::get('pharmacy', 'index')->name('pharmacy.index');
        Route::get('pharmacy/prescriptions/{prescription}', 'show')->name('pharmacy.show');
        Route::post('pharmacy/prescriptions/{prescription}/price', 'price')->name('pharmacy.price');
        Route::post('pharmacy/prescriptions/{prescription}/dispense', 'dispense')->name('pharmacy.dispense');
    });
    Route::middleware('permission:inventory.manage')->group(function () {
        Route::get('inventory/receive', [InventoryController::class, 'receiveForm'])->name('inventory.receive');
        Route::post('inventory/receive', [InventoryController::class, 'receive'])->name('inventory.receive.store');
        Route::post('inventory/batches/{batch}/adjust', [InventoryController::class, 'adjust'])->name('inventory.adjust');
    });
    Route::resource('suppliers', SupplierController::class)->except(['show', 'destroy'])
        ->middleware('permission:inventory.manage|purchasing.manage');

    // General stores & procurement
    Route::middleware('permission:stores.view')->controller(StoreController::class)->group(function () {
        Route::get('stores', 'index')->name('stores.index');
        Route::get('stores/items/{item}', 'show')->name('stores.show');
    });
    Route::middleware('permission:stores.manage')->controller(StoreController::class)->group(function () {
        Route::get('stores/receive', 'receiveForm')->name('stores.receive');
        Route::post('stores/receive', 'receive')->name('stores.receive.store');
        Route::post('stores/items/{item}/adjust', 'adjust')->name('stores.adjust');
    });
    Route::middleware('permission:requisitions.create|stores.view')->controller(RequisitionController::class)->group(function () {
        Route::get('requisitions', 'index')->name('requisitions.index');
        Route::get('requisitions/{requisition}', 'show')->name('requisitions.show')->whereNumber('requisition');
    });
    Route::middleware('permission:requisitions.create')->controller(RequisitionController::class)->group(function () {
        Route::get('requisitions/create', 'create')->name('requisitions.create');
        Route::post('requisitions', 'store')->name('requisitions.store');
        Route::post('requisitions/{requisition}/cancel', 'cancel')->name('requisitions.cancel');
    });
    Route::middleware('permission:stores.manage')->controller(RequisitionController::class)->group(function () {
        Route::post('requisitions/{requisition}/issue', 'issue')->name('requisitions.issue');
        Route::post('requisitions/{requisition}/reject', 'reject')->name('requisitions.reject');
    });
    Route::middleware('permission:purchasing.manage|purchasing.approve')->controller(PurchaseOrderController::class)->group(function () {
        Route::get('purchasing', 'index')->name('purchasing.index');
        Route::get('purchasing/orders/{order}', 'show')->name('purchasing.show')->whereNumber('order');
        Route::get('purchasing/orders/{order}/print', 'print')->name('purchasing.print');
    });
    Route::middleware('permission:purchasing.manage')->controller(PurchaseOrderController::class)->group(function () {
        Route::get('purchasing/orders/create', 'create')->name('purchasing.create');
        Route::post('purchasing/orders', 'store')->name('purchasing.store');
        Route::post('purchasing/orders/{order}/receive', 'receive')->name('purchasing.receive');
        Route::post('purchasing/orders/{order}/close', 'closeShort')->name('purchasing.close');
    });
    Route::middleware('permission:purchasing.approve')->controller(PurchaseOrderController::class)->group(function () {
        Route::post('purchasing/orders/{order}/approve', 'approve')->name('purchasing.approve');
    });
    Route::post('purchasing/orders/{order}/cancel', [PurchaseOrderController::class, 'cancel'])->name('purchasing.cancel')
        ->middleware('permission:purchasing.manage|purchasing.approve');
    Route::middleware('permission:payables.manage')->controller(SupplierInvoiceController::class)->group(function () {
        Route::get('purchasing/invoices', 'index')->name('invoices.index');
        Route::post('purchasing/invoices', 'store')->name('invoices.store');
        Route::post('purchasing/invoices/{invoice}/pay', 'pay')->name('invoices.pay');
    });
    Route::middleware('permission:inventory.view')->group(function () {
        Route::get('inventory', [InventoryController::class, 'index'])->name('inventory.index');
        Route::get('inventory/drugs/{drug}', [InventoryController::class, 'show'])->name('inventory.show');
    });

    // Billing
    Route::middleware('permission:billing.view')->controller(BillingController::class)->group(function () {
        Route::get('billing', 'index')->name('billing.index');
        Route::get('billing/patients/{patient}', 'account')->name('billing.account');
        Route::get('billing/payments/{payment}/receipt', 'receipt')->name('billing.receipt');
        Route::get('billing/bills/{bill}', 'invoice')->name('billing.invoice');
    });
    Route::middleware('permission:billing.collect')->controller(BillingController::class)->group(function () {
        Route::post('billing/patients/{patient}/payments', 'pay')->name('billing.pay');
        Route::post('billing/patients/{patient}/charges', 'addCharge')->name('billing.charge');
        Route::post('billing/patients/{patient}/deposits', 'deposit')->name('billing.deposit');
        Route::post('billing/patients/{patient}/payment-link', [\App\Http\Controllers\OnlinePaymentController::class, 'link'])->name('billing.payment-link');
    });
    Route::middleware('permission:billing.discount')->controller(BillingController::class)->group(function () {
        Route::post('billing/items/{item}/discount', 'discount')->name('billing.discount');
        Route::post('billing/items/{item}/void', 'voidItem')->name('billing.void-item');
    });
    Route::post('billing/payments/{payment}/reverse', [BillingController::class, 'reverse'])->name('billing.reverse')
        ->middleware('permission:billing.reverse');
    Route::middleware('permission:billing.prices')->group(function () {
        Route::get('billing/prices', [PriceListController::class, 'index'])->name('billing.prices');
        Route::post('billing/prices', [PriceListController::class, 'update'])->name('billing.prices.update');
    });
    Route::middleware('permission:billing.claims')->controller(ClaimController::class)->group(function () {
        Route::get('billing/claims', 'index')->name('billing.claims');
        Route::post('billing/claims/batches', 'storeBatch')->name('billing.claims.batches.store');
        Route::get('billing/claims/batches/{batch}', 'showBatch')->name('billing.claims.batch');
        Route::delete('billing/claims/batches/{batch}', 'destroyBatch')->name('billing.claims.batches.destroy');
        Route::post('billing/claims/batches/{batch}/submit', 'submit')->name('billing.claims.submit');
        Route::post('billing/claims/batches/{batch}/remit', 'remit')->name('billing.claims.remit');
        Route::get('billing/claims/batches/{batch}/export', 'export')->name('billing.claims.export');
        Route::get('billing/claims/batches/{batch}/print', 'printBatch')->name('billing.claims.print');
        Route::get('billing/claims/batches/{batch}/electronic', 'electronicFile')->name('billing.claims.electronic');
        Route::post('billing/claims/batches/{batch}/send', 'sendElectronic')->name('billing.claims.send');
        Route::delete('billing/claims/batches/{batch}/bills/{bill}', 'removeBill')->name('billing.claims.remove-bill');
        Route::get('billing/claims/bills/{bill}/form', 'form')->name('billing.claims.form');
        Route::post('billing/claims/bills/{bill}/pa-code', 'authorizationCode')->name('billing.claims.pa-code');
        Route::post('billing/claims/bills/{bill}/requeue', 'requeue')->name('billing.claims.requeue');
        Route::post('billing/claims/bills/{bill}/transfer', 'transfer')->name('billing.claims.transfer');
    });
    Route::middleware('permission:claims.preauth')->controller(PreauthorizationController::class)->group(function () {
        Route::get('billing/preauthorizations', 'index')->name('preauth.index');
        Route::post('billing/patients/{patient}/preauthorizations', 'store')->name('preauth.store');
        Route::post('billing/preauthorizations/{preauthorization}/decide', 'decide')->name('preauth.decide');
    });

    // Inpatients
    Route::middleware('permission:admissions.view')->controller(InpatientController::class)->group(function () {
        Route::get('inpatients', 'index')->name('inpatients.index');
        Route::get('admissions/{admission}', 'show')->name('inpatients.show');
        Route::get('admissions/{admission}/summary', 'summary')->name('inpatients.summary');
    });
    Route::middleware('permission:admissions.manage')->controller(InpatientController::class)->group(function () {
        Route::get('patients/{patient}/admit', 'create')->name('inpatients.create');
        Route::post('patients/{patient}/admit', 'store')->name('inpatients.store');
        Route::post('admissions/{admission}/transfer', 'transfer')->name('inpatients.transfer');
        Route::post('admissions/{admission}/bed-charges', 'chargeBeds')->name('inpatients.charge-beds');
    });
    Route::middleware('permission:admissions.discharge')->controller(InpatientController::class)->group(function () {
        Route::get('admissions/{admission}/discharge', 'dischargeForm')->name('inpatients.discharge');
        Route::post('admissions/{admission}/discharge', 'discharge')->name('inpatients.discharge.store');
    });
    Route::post('admissions/{admission}/notes', [InpatientController::class, 'note'])->name('inpatients.notes')->middleware('permission:admissions.notes');
    Route::post('admissions/{admission}/administrations', [InpatientController::class, 'administer'])->name('inpatients.administer')->middleware('permission:medication.administer');
    Route::middleware('permission:prescriptions.create')->group(function () {
        Route::post('admissions/{admission}/prescriptions', [InpatientController::class, 'prescribe'])->name('inpatients.prescribe');
        Route::post('prescription-items/{item}/stop', [InpatientController::class, 'stopMedication'])->name('inpatients.stop-medication');
    });
    Route::post('admissions/{admission}/lab-orders', [InpatientController::class, 'orderLab'])->name('inpatients.lab')->middleware('permission:lab.request');
    Route::post('admissions/{admission}/imaging-orders', [InpatientController::class, 'orderImaging'])->name('inpatients.imaging')->middleware('permission:imaging.request');
    Route::patch('ward-orders/{type}/{id}/cancel', [InpatientController::class, 'cancelOrder'])->name('inpatients.cancel-order')
        ->whereIn('type', ['lab', 'imaging'])->whereNumber('id')->middleware('permission:lab.request|imaging.request');

    // Maternity
    Route::middleware('permission:maternity.view')->controller(MaternityController::class)->group(function () {
        Route::get('maternity', 'index')->name('maternity.index');
        Route::get('pregnancies/{pregnancy}', 'show')->name('maternity.show');
        Route::get('pregnancies/{pregnancy}/partograph', 'partograph')->name('maternity.partograph');
    });
    Route::middleware('permission:maternity.record')->controller(MaternityController::class)->group(function () {
        Route::get('patients/{patient}/anc/book', 'create')->name('maternity.create');
        Route::post('patients/{patient}/anc/book', 'store')->name('maternity.store');
        Route::post('pregnancies/{pregnancy}/anc-visits', 'storeAnc')->name('maternity.anc');
        Route::post('pregnancies/{pregnancy}/labour', 'startLabour')->name('maternity.labour');
        Route::post('pregnancies/{pregnancy}/partograph', 'storePartograph')->name('maternity.partograph.store');
        Route::get('pregnancies/{pregnancy}/delivery', 'deliveryForm')->name('maternity.delivery');
        Route::post('pregnancies/{pregnancy}/delivery', 'storeDelivery')->name('maternity.delivery.store');
        Route::post('pregnancies/{pregnancy}/postnatal', 'storePostnatal')->name('maternity.postnatal');
        Route::post('pregnancies/{pregnancy}/end', 'end')->name('maternity.end');
    });

    // Immunization (viewing a child's card only needs patient access)
    // Specialty clinics: dental, eye, physiotherapy
    Route::middleware('permission:specialty.view')->controller(SpecialtyController::class)->group(function () {
        Route::get('patients/{patient}/dental', 'dental')->name('specialty.dental');
        Route::get('patients/{patient}/eye', 'eye')->name('specialty.eye');
        Route::get('eye-exams/{exam}/spectacles', 'spectacles')->name('specialty.spectacles');
        Route::get('patients/{patient}/physio', 'physio')->name('specialty.physio');
        Route::get('physio/{episode}', 'physioEpisode')->name('specialty.physio.show');
    });
    Route::middleware('permission:dental.record')->controller(SpecialtyController::class)->group(function () {
        Route::post('patients/{patient}/dental', 'storeDental')->name('specialty.dental.store');
        Route::patch('dental-findings/{finding}/complete', 'completeDental')->name('specialty.dental.complete');
        Route::patch('dental-findings/{finding}/cancel', 'cancelDental')->name('specialty.dental.cancel');
    });
    Route::post('patients/{patient}/eye', [SpecialtyController::class, 'storeEye'])->name('specialty.eye.store')->middleware('permission:eye.record');
    Route::middleware('permission:physio.record')->controller(SpecialtyController::class)->group(function () {
        Route::post('patients/{patient}/physio', 'storePhysio')->name('specialty.physio.store');
        Route::post('physio/{episode}/sessions', 'storeSession')->name('specialty.physio.session');
        Route::post('physio/{episode}/discharge', 'discharge')->name('specialty.physio.discharge');
    });

    Route::get('immunizations', [ImmunizationController::class, 'index'])->name('immunizations.index')->middleware('permission:immunization.record');
    Route::middleware('permission:patients.view')->group(function () {
        Route::get('patients/{patient}/immunizations', [ImmunizationController::class, 'show'])->name('immunizations.show');
        Route::get('patients/{patient}/immunizations/card', [ImmunizationController::class, 'card'])->name('immunizations.card');
    });
    Route::post('patients/{patient}/immunizations', [ImmunizationController::class, 'store'])->name('immunizations.store')
        ->middleware('permission:immunization.record');

    // Theatre
    Route::middleware('permission:theatre.view')->controller(TheatreController::class)->group(function () {
        Route::get('theatre', 'index')->name('theatre.index');
        Route::get('surgeries/{surgery}', 'show')->name('theatre.show');
        Route::get('surgeries/{surgery}/note', 'note')->name('theatre.note');
    });
    Route::middleware('permission:theatre.book')->controller(TheatreController::class)->group(function () {
        Route::get('patients/{patient}/surgery/book', 'create')->name('theatre.create');
        Route::post('patients/{patient}/surgery/book', 'store')->name('theatre.store');
        Route::get('surgeries/{surgery}/edit', 'edit')->name('theatre.edit');
        Route::put('surgeries/{surgery}', 'update')->name('theatre.update');
        Route::post('surgeries/{surgery}/stop', 'stop')->name('theatre.stop');
    });
    Route::middleware('permission:theatre.manage')->controller(TheatreController::class)->group(function () {
        Route::post('surgeries/{surgery}/preop', 'preop')->name('theatre.preop');
        Route::post('surgeries/{surgery}/checklist/{phase}', 'checklist')->name('theatre.checklist')->whereIn('phase', array_keys(\App\Models\Surgery::CHECKLIST));
        Route::post('surgeries/{surgery}/observations', 'observe')->name('theatre.observe');
        Route::post('surgeries/{surgery}/anaesthesia', 'anaesthesia')->name('theatre.anaesthesia');
    });
    Route::post('surgeries/{surgery}/complete', [TheatreController::class, 'complete'])->name('theatre.complete')->middleware('permission:theatre.operate');

    // Reports (each report checks its own permission)
    Route::middleware('permission:reports.operational|reports.clinical|reports.financial|reports.staff')->group(function () {
        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/{report}', [ReportController::class, 'show'])->name('reports.show');
    });

    // Appointments
    Route::middleware('permission:appointments.manage')->group(function () {
        Route::get('appointments/create', [AppointmentController::class, 'create'])->name('appointments.create');
        Route::post('appointments', [AppointmentController::class, 'store'])->name('appointments.store');
        Route::get('appointments/{appointment}/edit', [AppointmentController::class, 'edit'])->name('appointments.edit');
        Route::put('appointments/{appointment}', [AppointmentController::class, 'update'])->name('appointments.update');
        Route::patch('appointments/{appointment}/cancel', [AppointmentController::class, 'cancel'])->name('appointments.cancel');
        Route::patch('appointments/{appointment}/no-show', [AppointmentController::class, 'noShow'])->name('appointments.no-show');
        Route::get('appointments/requests', [AppointmentController::class, 'requests'])->name('appointments.requests');
        Route::patch('appointments/{appointment}/confirm', [AppointmentController::class, 'confirm'])->name('appointments.confirm');
    });
    Route::get('appointments', [AppointmentController::class, 'index'])->name('appointments.index')
        ->middleware('permission:appointments.view');

    Route::prefix('admin')->name('admin.')->group(function () {
        // Each catalogue checks its own permission (store items: stores.manage).
        Route::middleware('permission:catalog.manage|stores.manage')->controller(CatalogController::class)
            ->whereIn('catalog', ['services', 'lab-tests', 'imaging', 'drugs', 'vaccines', 'procedures', 'theatres', 'store-items'])->group(function () {
                Route::get('catalogs/{catalog}', 'index')->name('catalogs.index');
                Route::get('catalogs/{catalog}/create', 'create')->name('catalogs.create');
                Route::post('catalogs/{catalog}', 'store')->name('catalogs.store');
                Route::get('catalogs/{catalog}/{id}/edit', 'edit')->name('catalogs.edit')->whereNumber('id');
                Route::put('catalogs/{catalog}/{id}', 'update')->name('catalogs.update')->whereNumber('id');
            });

        Route::middleware('permission:catalog.manage')->controller(LabParameterController::class)->group(function () {
            Route::get('lab-tests/{labTest}/parameters', 'index')->name('lab-parameters.index');
            Route::post('lab-tests/{labTest}/parameters', 'store')->name('lab-parameters.store');
            Route::put('lab-tests/{labTest}/parameters/{parameter}', 'update')->name('lab-parameters.update');
            Route::delete('lab-tests/{labTest}/parameters/{parameter}', 'destroy')->name('lab-parameters.destroy');
        });

        Route::middleware('permission:integrations.manage')->controller(IntegrationController::class)->group(function () {
            Route::get('integrations', 'index')->name('integrations.index');
            Route::post('integrations/pacs/test', 'testPacs')->name('integrations.test-pacs');
            Route::get('integrations/messages/{message}', 'message')->name('integrations.message');
            Route::post('integrations/analyzers', 'storeAnalyzer')->name('integrations.analyzers.store');
            Route::get('integrations/analyzers/{analyzer}', 'analyzer')->name('integrations.analyzer');
            Route::post('integrations/analyzers/{analyzer}/mappings', 'saveMappings')->name('integrations.analyzers.mappings');
            Route::post('integrations/analyzers/{analyzer}/token', 'regenerateToken')->name('integrations.analyzers.token');
            Route::post('integrations/analyzers/{analyzer}/toggle', 'toggleAnalyzer')->name('integrations.analyzers.toggle');
        });

        Route::middleware('permission:data.import')->controller(ImportController::class)->group(function () {
            Route::get('imports', 'index')->name('imports.index');
            Route::get('imports/history', 'history')->name('imports.history');
            Route::get('imports/runs/{import}', 'review')->name('imports.review');
            Route::post('imports/runs/{import}/run', 'run')->name('imports.run');
            Route::post('imports/runs/{import}/cancel', 'cancel')->name('imports.cancel');
            Route::get('imports/runs/{import}/errors', 'errors')->name('imports.errors');
            Route::get('imports/{type}', 'show')->name('imports.show');
            Route::get('imports/{type}/template', 'template')->name('imports.template');
            Route::post('imports/{type}', 'upload')->name('imports.upload');
        });

        Route::middleware('permission:system.manage')->controller(SystemController::class)->group(function () {
            Route::get('system', 'index')->name('system.index');
            Route::post('system/backups', 'backup')->name('system.backup');
            Route::get('system/backups/{name}', 'download')->name('system.download');
        });

        Route::middleware('permission:sms.manage')->controller(SmsController::class)->group(function () {
            Route::get('sms', 'index')->name('sms.index');
            Route::post('sms/test', 'test')->name('sms.test');
            Route::post('sms/{message}/retry', 'retry')->name('sms.retry');
        });

        Route::middleware('permission:wards.manage')->group(function () {
            Route::resource('wards', WardController::class)->except(['show', 'destroy']);
            Route::post('wards/{ward}/beds', [WardController::class, 'addBedsAction'])->name('wards.beds');
            Route::patch('beds/{bed}/status', [WardController::class, 'bedStatus'])->name('beds.status');
        });

        Route::resource('clinics', ClinicController::class)->except('show')
            ->middleware('permission:clinics.manage');

        Route::resource('insurance', InsuranceProviderController::class)->except('show')
            ->parameters(['insurance' => 'insurance'])
            ->middleware('permission:insurance.manage');

        Route::middleware('permission:settings.manage')->group(function () {
            Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
            Route::post('settings/{section}', [SettingsController::class, 'update'])->name('settings.update');
        });

        // Staff: viewing and managing are separate permissions.
        Route::middleware('permission:users.manage')->group(function () {
            Route::get('users/create', [UserController::class, 'create'])->name('users.create');
            Route::post('users', [UserController::class, 'store'])->name('users.store');
            Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
            Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
            Route::patch('users/{user}/status', [UserController::class, 'toggleStatus'])->name('users.status');
            Route::put('users/{user}/password', [UserController::class, 'resetPassword'])->name('users.password');
            Route::post('users/{user}/unlock', [UserController::class, 'unlock'])->name('users.unlock');
        });
        Route::middleware('permission:users.view')->group(function () {
            Route::get('users', [UserController::class, 'index'])->name('users.index');
            Route::get('users/{user}', [UserController::class, 'show'])->name('users.show');
        });

        Route::resource('departments', DepartmentController::class)->except('show')
            ->middleware('permission:departments.manage');

        Route::resource('roles', RoleController::class)->except('show')
            ->middleware('permission:roles.manage');

        Route::middleware('permission:audit.view')->group(function () {
            Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit.index');
            Route::get('audit-logs/{auditLog}', [AuditLogController::class, 'show'])->name('audit.show');
        });
    });
});

// Online payments: public pages the gateway / payment link lead to (the gateway is always re-asked).
Route::get('payments/callback', [\App\Http\Controllers\OnlinePaymentController::class, 'callback'])->name('payments.callback');
Route::get('pay/{reference}', [\App\Http\Controllers\OnlinePaymentController::class, 'pay'])->name('payments.pay')->where('reference', 'EMR-[A-Z0-9]{24}');

require __DIR__.'/portal.php';
