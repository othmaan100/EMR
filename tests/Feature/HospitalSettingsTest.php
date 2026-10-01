<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HospitalSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->markInstalled();
    }

    public function test_staff_without_permission_cannot_access_admin_pages(): void
    {
        $nurse = User::factory()->create()->assignRole('Nurse');

        $this->actingAs($nurse)->get(route('admin.settings.edit'))->assertForbidden();
        $this->actingAs($nurse)->get(route('admin.audit.index'))->assertForbidden();
        $this->actingAs($nurse)->get('/dashboard')->assertOk()->assertDontSee('Hospital Settings');
    }

    public function test_administrator_role_can_manage_settings(): void
    {
        $admin = User::factory()->create()->assignRole('Administrator');

        $this->actingAs($admin)->get(route('admin.settings.edit', ['section' => 'contact']))->assertOk()->assertSee('Street address');
    }

    public function test_updating_settings_is_saved_and_audited(): void
    {
        $this->actingAs($this->superAdmin())
            ->post(route('admin.settings.update', 'contact'), [
                'address' => '1 New Road', 'city' => 'Accra', 'country' => 'Ghana', 'phone' => '0200000000',
            ])
            ->assertRedirect(route('admin.settings.edit', ['section' => 'contact']));

        $this->assertSame('Accra', setting('city'));
        $this->assertDatabaseHas('audit_logs', ['event' => 'settings_updated']);
    }

    public function test_logo_can_be_replaced_and_removed(): void
    {
        Storage::fake('uploads');
        $admin = $this->superAdmin();
        $profile = ['hospital_name' => 'Test Hospital', 'hospital_type' => 'Clinic'];

        $this->actingAs($admin)->post(route('admin.settings.update', 'profile'), $profile + ['logo' => UploadedFile::fake()->image('a.png')]);
        $first = str_replace('uploads/', '', setting('logo'));
        Storage::disk('uploads')->assertExists($first);

        $this->actingAs($admin)->post(route('admin.settings.update', 'profile'), $profile + ['logo' => UploadedFile::fake()->image('b.jpg')]);
        Storage::disk('uploads')->assertMissing($first);
        $second = str_replace('uploads/', '', setting('logo'));
        Storage::disk('uploads')->assertExists($second);

        $this->actingAs($admin)->post(route('admin.settings.update', 'profile'), $profile + ['remove_logo' => 1]);
        Storage::disk('uploads')->assertMissing($second);
        $this->assertNull(app(\App\Support\Settings::class)->all()['logo']);
    }

    public function test_unknown_section_returns_404(): void
    {
        $this->actingAs($this->superAdmin())->post(route('admin.settings.update', 'hacking'))->assertNotFound();
    }

    public function test_audit_log_page_lists_user_changes(): void
    {
        $admin = $this->superAdmin();
        $staff = User::factory()->create(['name' => 'Old Name']);
        $staff->update(['name' => 'New Name', 'password' => 'changed-secret']);

        $this->actingAs($admin)->get(route('admin.audit.index', ['event' => 'updated']))
            ->assertOk()
            ->assertSee('User #'.$staff->id.' updated');

        $log = \App\Models\AuditLog::where('event', 'updated')->latest('id')->first();
        $this->assertSame(['name' => 'New Name'], $log->new_values);
        $this->assertArrayNotHasKey('password', $log->old_values);

        $this->actingAs($admin)->get(route('admin.audit.show', $log))->assertOk()->assertSee('Old Name');
    }
}
