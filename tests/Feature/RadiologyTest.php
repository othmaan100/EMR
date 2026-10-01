<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\Consultation;
use App\Models\ImagingAttachment;
use App\Models\ImagingOrder;
use App\Models\ImagingTest;
use App\Models\Patient;
use App\Models\User;
use App\Models\Visit;
use App\Services\QueueService;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RadiologyTest extends TestCase
{
    use RefreshDatabase;

    protected User $doctor;

    protected User $radiographer;

    protected User $radiologist;

    protected Patient $patient;

    protected ImagingOrder $order;

    protected function setUp(): void
    {
        parent::setUp();
        $this->markInstalled();
        $this->seed(CatalogSeeder::class);
        Storage::fake('local');

        $this->doctor = User::factory()->create()->assignRole('Doctor');
        $this->radiographer = User::factory()->create()->assignRole('Radiographer');
        $this->radiologist = User::factory()->create()->assignRole('Radiologist');
        $this->patient = Patient::factory()->create();

        $clinic = Clinic::factory()->create(['requires_triage' => false]);
        $visit = app(QueueService::class)->checkIn($this->patient, ['clinic_id' => $clinic->id], $this->doctor);
        $this->actingAs($this->doctor)->patch(route('visits.move', $visit), ['status' => Visit::IN_CONSULTATION]);
        $this->actingAs($this->doctor)->get(route('consultations.show', $visit));
        $this->actingAs($this->doctor)->post(route('consultations.imaging.store', Consultation::firstOrFail()), [
            'imaging_test_id' => ImagingTest::where('code', 'XR-CHEST')->value('id'), 'priority' => 'routine', 'clinical_notes' => 'Cough 3 weeks',
        ]);
        $this->order = ImagingOrder::firstOrFail();
    }

    protected function performAndUpload(): ImagingAttachment
    {
        $this->actingAs($this->radiographer)->post(route('radiology.perform', $this->order))->assertRedirect(route('radiology.show', $this->order));
        $this->actingAs($this->radiographer)->post(route('radiology.upload', $this->order), [
            'files' => [UploadedFile::fake()->image('pa.jpg', 800, 800), UploadedFile::fake()->create('old-report.pdf', 50, 'application/pdf')],
            'caption' => 'PA view',
        ])->assertSessionHasNoErrors();

        return ImagingAttachment::firstOrFail();
    }

    public function test_worklist_schedule_and_perform(): void
    {
        $this->actingAs($this->radiographer)->get(route('radiology.index'))->assertOk()->assertSee($this->order->order_number);

        $this->actingAs($this->radiographer)->post(route('radiology.schedule', $this->order), [
            'date' => now()->addDay()->toDateString(), 'time' => '10:30',
        ])->assertSessionHas('success');
        $this->assertSame('scheduled', $this->order->fresh()->status);

        $this->performAndUpload();
        $this->order->refresh();
        $this->assertSame('performed', $this->order->status);
        $this->assertSame($this->radiographer->id, $this->order->performed_by);
        // Findings pre-filled from the normal template.
        $this->assertStringContainsString('costophrenic angles are clear', $this->order->findings);
        $this->assertSame(2, $this->order->attachments()->count());
        Storage::disk('local')->assertExists(ImagingAttachment::first()->path);
    }

    public function test_only_images_and_pdfs_are_accepted(): void
    {
        $this->actingAs($this->radiographer)->post(route('radiology.upload', $this->order), [
            'files' => [UploadedFile::fake()->create('virus.exe', 10, 'application/octet-stream')],
        ])->assertSessionHasErrors('files.0');
    }

    public function test_radiographer_cannot_report(): void
    {
        $this->performAndUpload();

        $this->actingAs($this->radiographer)->post(route('radiology.report.save', $this->order), ['findings' => 'x', 'impression' => 'y', 'sign' => 1])
            ->assertForbidden();
        $this->actingAs($this->radiographer)->get(route('radiology.show', $this->order))->assertOk()->assertSee('Waiting for a radiologist');
    }

    public function test_report_draft_sign_and_release(): void
    {
        $file = $this->performAndUpload();

        // Clinician can't see anything yet.
        $this->actingAs($this->doctor)->get(route('radiology.report', $this->order))->assertForbidden();
        $this->actingAs($this->doctor)->get(route('radiology.attachment', $file))->assertForbidden();

        $this->actingAs($this->radiologist)->post(route('radiology.report.save', $this->order), [
            'technique' => 'PA erect', 'findings' => 'Right upper zone cavitating opacity.', 'impression' => '', 'sign' => 0,
        ])->assertSessionHas('success');
        $this->assertSame('performed', $this->order->fresh()->status);

        // Signing requires an impression.
        $this->actingAs($this->radiologist)->post(route('radiology.report.save', $this->order), [
            'findings' => 'Right upper zone cavitating opacity.', 'impression' => '', 'sign' => 1,
        ])->assertSessionHasErrors('impression');

        $this->actingAs($this->radiologist)->post(route('radiology.report.save', $this->order), [
            'technique' => 'PA erect', 'findings' => 'Right upper zone cavitating opacity.',
            'impression' => 'Features suggestive of pulmonary tuberculosis.', 'sign' => 1,
        ])->assertRedirect(route('radiology.index', ['tab' => 'report']));

        $this->order->refresh();
        $this->assertSame('completed', $this->order->status);
        $this->assertSame($this->radiologist->id, $this->order->reported_by);
        $this->assertDatabaseHas('audit_logs', ['event' => 'imaging_report_signed']);

        // Now visible to clinicians everywhere.
        $this->actingAs($this->doctor)->get(route('radiology.report', $this->order))->assertOk()
            ->assertSee('pulmonary tuberculosis')->assertDontSee('DRAFT');
        $this->actingAs($this->doctor)->get(route('radiology.attachment', $file))->assertOk();
        $this->actingAs($this->doctor)->get(route('consultations.show', $this->order->visit_id))->assertOk()->assertSee('pulmonary tuberculosis');
        $this->actingAs($this->doctor)->get(route('patients.show', $this->patient))->assertOk()->assertSee('pulmonary tuberculosis');

        // Locked after signing.
        $this->actingAs($this->radiologist)->post(route('radiology.report.save', $this->order), ['findings' => 'changed', 'impression' => 'x'])
            ->assertSessionHasErrors('status');
        $this->actingAs($this->radiographer)->delete(route('radiology.detach', $file))->assertSessionHasErrors('status');
    }

    public function test_attachment_can_be_removed_before_signing(): void
    {
        $file = $this->performAndUpload();

        $this->actingAs($this->radiographer)->delete(route('radiology.detach', $file))->assertSessionHas('success');
        $this->assertModelMissing($file);
        Storage::disk('local')->assertMissing($file->path);
    }

    public function test_permissions_and_pages(): void
    {
        $nurse = User::factory()->create()->assignRole('Nurse');
        $this->actingAs($nurse)->get(route('radiology.index'))->assertForbidden();
        $this->actingAs($this->doctor)->post(route('radiology.perform', $this->order))->assertForbidden();

        foreach (['perform', 'report', 'done'] as $tab) {
            $this->actingAs($this->radiologist)->get(route('radiology.index', ['tab' => $tab]))->assertOk();
        }
        $this->actingAs($this->radiologist)->get(route('radiology.show', $this->order))->assertOk();
        $this->actingAs($this->radiologist)->get(route('radiology.report', $this->order))->assertOk()->assertSee('DRAFT');

        $admin = User::factory()->create()->assignRole('Administrator');
        $this->actingAs($admin)->get(route('admin.catalogs.edit', ['imaging', $this->order->imaging_test_id]))->assertOk()->assertSee('Normal report template');
    }
}
