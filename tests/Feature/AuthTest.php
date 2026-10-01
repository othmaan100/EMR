<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->markInstalled();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->get('/login')->assertOk();
    }

    public function test_user_can_sign_in_with_username_or_email(): void
    {
        $user = User::factory()->create(['username' => 'nurse1', 'email' => 'nurse@h.test']);

        $this->post('/login', ['login' => 'nurse1', 'password' => 'password'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertDatabaseHas('audit_logs', ['event' => 'login', 'user_id' => $user->id]);

        $this->post('/logout');
        $this->assertGuest();

        $this->post('/login', ['login' => 'nurse@h.test', 'password' => 'password'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_wrong_password_is_rejected_and_logged(): void
    {
        User::factory()->create(['username' => 'doc']);

        $this->post('/login', ['login' => 'doc', 'password' => 'wrong'])->assertSessionHasErrors('login');
        $this->assertGuest();
        $this->assertSame(1, AuditLog::where('event', 'login_failed')->count());
    }

    public function test_inactive_user_cannot_sign_in(): void
    {
        User::factory()->inactive()->create(['username' => 'gone']);

        $this->post('/login', ['login' => 'gone', 'password' => 'password'])->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_deactivated_user_is_signed_out_on_next_request(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $user->update(['is_active' => false]);

        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_login_is_rate_limited(): void
    {
        User::factory()->create(['username' => 'doc']);

        foreach (range(1, 5) as $_) {
            $this->post('/login', ['login' => 'doc', 'password' => 'wrong']);
        }

        $this->post('/login', ['login' => 'doc', 'password' => 'password'])->assertSessionHasErrors('login');
        $this->assertStringContainsString('Too many sign-in attempts', session('errors')->first('login'));
        $this->assertGuest();
    }
}
