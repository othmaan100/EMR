<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->markInstalled();
    }

    public function test_user_with_temporary_password_is_forced_to_change_it(): void
    {
        $user = User::factory()->create(['must_change_password' => true]);

        $this->actingAs($user)->get('/dashboard')->assertRedirect(route('password.force'));
        $this->actingAs($user)->get(route('password.force'))->assertOk()->assertSee('Set your own password');

        $this->actingAs($user)->put(route('account.password.update'), [
            'current_password' => 'password',
            'password' => 'MyNewPass1',
            'password_confirmation' => 'MyNewPass1',
        ])->assertRedirect(route('dashboard'));

        $this->assertFalse($user->fresh()->must_change_password);
        $this->actingAs($user->fresh())->get('/dashboard')->assertOk();
    }

    public function test_password_change_requires_correct_current_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('account.password.update'), [
            'current_password' => 'wrong',
            'password' => 'MyNewPass1',
            'password_confirmation' => 'MyNewPass1',
        ])->assertSessionHasErrorsIn('password', 'current_password');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_new_password_must_differ_and_be_strong(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('account.password.update'), [
            'current_password' => 'password',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrorsIn('password', 'password');
    }

    public function test_user_can_update_own_profile(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('account.profile'))->assertOk();
        $this->actingAs($user)->put(route('account.profile.update'), [
            'name' => 'Updated Name', 'email' => 'new@h.test', 'phone' => '0800',
        ])->assertSessionHas('success');

        $this->assertSame('Updated Name', $user->fresh()->name);
    }
}
