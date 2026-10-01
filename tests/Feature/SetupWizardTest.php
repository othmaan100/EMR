<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Installer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SetupWizardTest extends TestCase
{
    use RefreshDatabase;

    public function test_uninstalled_app_redirects_everything_to_setup(): void
    {
        $this->get('/login')->assertRedirect(route('setup.index'));
        $this->get('/dashboard')->assertRedirect(route('setup.index'));
        $this->get('/setup')->assertRedirect(route('setup.step', 'requirements'));
    }

    public function test_steps_cannot_be_skipped(): void
    {
        $this->get(route('setup.step', 'admin'))->assertRedirect(route('setup.step', 'requirements'));
        $this->post(route('setup.store', 'profile'), ['hospital_name' => 'X'])->assertRedirect(route('setup.step', 'requirements'));
    }

    public function test_full_wizard_configures_hospital_and_creates_super_admin(): void
    {
        Storage::fake('uploads');

        $this->get(route('setup.step', 'requirements'))->assertOk()->assertSee('System Check');

        $this->post(route('setup.store', 'requirements'))->assertRedirect(route('setup.step', 'profile'));

        $this->post(route('setup.store', 'profile'), [
            'hospital_name' => 'Unity Specialist Hospital',
            'hospital_short_name' => 'USH',
            'hospital_type' => 'Specialist Hospital',
            'motto' => 'Care with compassion',
            'logo' => UploadedFile::fake()->image('logo.png', 200, 200),
        ])->assertRedirect(route('setup.step', 'contact'));

        $this->post(route('setup.store', 'contact'), [
            'address' => '12 Hospital Road',
            'city' => 'Dutse',
            'state' => 'Jigawa',
            'country' => 'Nigeria',
            'phone' => '+2348000000000',
            'email' => 'info@ush.test',
        ])->assertRedirect(route('setup.step', 'preferences'));

        $this->post(route('setup.store', 'preferences'), [
            'currency_code' => 'KES',
            'timezone' => 'Africa/Nairobi',
            'date_format' => 'Y-m-d',
            'patient_number_prefix' => 'ush',
            'primary_color' => '#198754',
        ])->assertRedirect(route('setup.step', 'admin'));

        $this->post(route('setup.store', 'admin'), [
            'name' => 'Amina Bello',
            'username' => 'admin',
            'email' => 'admin@ush.test',
            'password' => 'Secret123',
            'password_confirmation' => 'Secret123',
        ])->assertRedirect(route('dashboard'));

        $this->assertTrue(app(Installer::class)->isInstalled());
        $this->assertSame('Unity Specialist Hospital', setting('hospital_name'));
        $this->assertSame('KSh', setting('currency_symbol'));
        $this->assertSame('USH', setting('patient_number_prefix'));
        Storage::disk('uploads')->assertExists(str_replace('uploads/', '', setting('logo')));

        $admin = User::where('username', 'admin')->first();
        $this->assertTrue($admin->isSuperAdmin());
        $this->assertAuthenticatedAs($admin);

        // Branding shows on the dashboard; wizard is now locked.
        $this->get('/dashboard')->assertOk()->assertSee('Unity Specialist Hospital')->assertSee('--brand: #198754', false);
        $this->get('/setup')->assertRedirect(route('login'));
    }

    public function test_profile_step_validates_input(): void
    {
        $this->post(route('setup.store', 'requirements'));

        $this->post(route('setup.store', 'profile'), [
            'hospital_name' => '',
            'hospital_type' => 'Spaceship',
            'logo' => UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml'),
        ])->assertSessionHasErrors(['hospital_name', 'hospital_type', 'logo']);
    }
}
