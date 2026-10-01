<?php

namespace Tests\Feature;

use App\Http\Controllers\ReportController;
use App\Http\Middleware\IdleTimeout;
use App\Models\User;
use App\Services\BackupService;
use App\Support\DatabaseDumper;
use App\Support\Settings;
use App\Support\SystemHealth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Tests\TestCase;
use ZipArchive;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->markInstalled();
    }

    public function test_security_headers_and_no_store_for_signed_in_pages(): void
    {
        $this->get(route('login'))
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $response = $this->actingAs($this->superAdmin())->get(route('dashboard'))->assertOk();
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_idle_session_is_signed_out(): void
    {
        app(Settings::class)->set('session_idle_minutes', 10);
        $user = $this->superAdmin();

        $this->actingAs($user)->withSession([IdleTimeout::KEY => time() - 5 * 60])->get(route('dashboard'))->assertOk();

        $this->actingAs($user)->withSession([IdleTimeout::KEY => time() - 11 * 60])->get(route('dashboard'))
            ->assertRedirect(route('login'))->assertSessionHas('warning');
        $this->assertGuest();
    }

    public function test_background_refresh_does_not_keep_session_alive_but_ping_does(): void
    {
        $user = $this->superAdmin();
        $old = time() - 60;

        $this->actingAs($user)->withSession([IdleTimeout::KEY => $old])
            ->get(route('dashboard'), ['X-Requested-With' => 'XMLHttpRequest']);
        $this->assertSame($old, session(IdleTimeout::KEY));

        $this->actingAs($user)->withSession([IdleTimeout::KEY => $old])->post(route('session.ping'))->assertNoContent();
        $this->assertGreaterThan($old, session(IdleTimeout::KEY));
    }

    public function test_account_locks_after_repeated_failures_and_admin_can_unlock(): void
    {
        $user = User::factory()->create(['username' => 'nurse1']);

        // Different IPs, so only the account lock (not the IP rate limiter) applies.
        foreach (range(1, 5) as $i) {
            $this->withServerVariables(['REMOTE_ADDR' => "10.0.0.$i"])->post('/login', ['login' => 'nurse1', 'password' => 'wrong']);
        }

        $this->assertTrue($user->fresh()->isLocked());
        $this->assertDatabaseHas('audit_logs', ['event' => 'account_locked']);

        // Correct password is refused while locked.
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.99'])
            ->post('/login', ['login' => 'nurse1', 'password' => 'password'])
            ->assertSessionHasErrors('login');
        $this->assertGuest();

        $admin = User::factory()->create()->assignRole('Administrator');
        $this->actingAs($admin)->get(route('admin.users.show', $user))->assertSee('Unlock now');
        $this->actingAs($admin)->post(route('admin.users.unlock', $user))->assertSessionHas('success');
        $this->assertFalse($user->fresh()->isLocked());
        auth()->logout();

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.100'])
            ->post('/login', ['login' => 'nurse1', 'password' => 'password'])->assertRedirect(route('dashboard'));
    }

    public function test_successful_login_resets_failure_count_and_shows_previous_sign_in(): void
    {
        $user = User::factory()->create(['username' => 'doc', 'last_login_at' => now()->subDay(), 'last_login_ip' => '192.168.1.50']);
        $this->post('/login', ['login' => 'doc', 'password' => 'wrong']);
        $this->assertSame(1, $user->fresh()->failed_login_attempts);

        $this->post('/login', ['login' => 'doc', 'password' => 'password']);
        $this->assertSame(0, $user->fresh()->failed_login_attempts);
        $this->get(route('dashboard'))->assertSee('Your previous sign-in')->assertSee('192.168.1.50');
    }

    public function test_csv_values_cannot_become_formulas(): void
    {
        $this->assertSame("'=HYPERLINK(\"x\")", ReportController::csvSafe('=HYPERLINK("x")'));
        $this->assertSame("'@SUM(A1)", ReportController::csvSafe('@SUM(A1)'));
        $this->assertSame('-12.5', ReportController::csvSafe('-12.5')); // negative numbers untouched
        $this->assertSame(-3, ReportController::csvSafe(-3));
        $this->assertSame('Malaria', ReportController::csvSafe('Malaria'));
    }

    public function test_backup_zips_database_and_files_and_prunes(): void
    {
        $dir = storage_path('framework/testing/backups');
        $files = storage_path('framework/testing/backup-src');
        File::deleteDirectory($dir);
        File::ensureDirectoryExists($files.'/patients');
        File::put($files.'/patients/photo.jpg', 'fake-image');
        config(['emr.backup.path' => $dir, 'emr.backup.folders' => [$files]]);

        // Fake dumper: tests run on in-memory SQLite.
        $this->app->instance(DatabaseDumper::class, new class extends DatabaseDumper {
            public function dump(string $target): void
            {
                file_put_contents($target, '-- test dump');
            }
        });

        // An old backup beyond retention is pruned; the new one is kept.
        File::ensureDirectoryExists($dir);
        touch($dir.'/emr-backup-2020-01-01_000000.zip', now()->subDays(60)->timestamp);

        $this->artisan('emr:backup')->assertSuccessful();

        $backups = app(BackupService::class)->list();
        $this->assertCount(1, $backups);
        $zip = new ZipArchive;
        $zip->open($dir.'/'.$backups[0]['name']);
        $this->assertSame('-- test dump', $zip->getFromName('database.sql'));
        $this->assertSame('fake-image', $zip->getFromName('files/backup-src/patients/photo.jpg'));
        $zip->close();
        $this->assertDatabaseHas('audit_logs', ['event' => 'backup_created']);

        // Only Super Admin may download; names are validated.
        $admin = User::factory()->create()->assignRole('Administrator');
        $this->actingAs($admin)->get(route('admin.system.download', $backups[0]['name']))->assertForbidden();
        $super = $this->superAdmin();
        $this->actingAs($super)->get(route('admin.system.download', $backups[0]['name']))->assertOk();
        $this->actingAs($super)->get(route('admin.system.download', '..%2F.env'))->assertNotFound();

        File::deleteDirectory($dir);
        File::deleteDirectory($files);
    }

    public function test_system_health_page_and_dashboard_banner(): void
    {
        config(['app.debug' => true, 'emr.backup.path' => storage_path('framework/testing/empty-backups')]);
        Cache::forget(SystemHealth::HEARTBEAT_KEY);
        Cache::forget('emr.health.failures');

        $admin = User::factory()->create()->assignRole('Administrator');
        $this->actingAs($admin)->get(route('dashboard'))->assertSee('System health');
        $this->actingAs($admin)->get(route('admin.system.index'))
            ->assertOk()->assertSee('Debug mode')->assertSee('APP_DEBUG=false')->assertSee('Has never run');

        $doctor = User::factory()->create()->assignRole('Doctor');
        $this->actingAs($doctor)->get(route('admin.system.index'))->assertForbidden();
        $this->actingAs($doctor)->get(route('dashboard'))->assertDontSee('System health');

        File::deleteDirectory(storage_path('framework/testing/empty-backups'));
    }

    public function test_security_settings_are_saved(): void
    {
        $this->actingAs($this->superAdmin())->post(route('admin.settings.update', 'security'), [
            'session_idle_minutes' => 20, 'backup_retention_days' => 30,
        ])->assertSessionHas('success');

        $this->assertSame('20', (string) setting('session_idle_minutes'));
        $this->actingAs($this->superAdmin())->post(route('admin.settings.update', 'security'), [
            'session_idle_minutes' => 1, 'backup_retention_days' => 30,
        ])->assertSessionHasErrors('session_idle_minutes');
    }
}
