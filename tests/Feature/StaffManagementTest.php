<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StaffManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->markInstalled();
    }

    protected function administrator(): User
    {
        return User::factory()->create()->assignRole('Administrator');
    }

    protected function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Grace Okafor',
            'username' => 'grace',
            'email' => 'grace@h.test',
            'staff_id' => 'EMP-001',
            'designation' => 'Staff Nurse',
            'roles' => ['Nurse'],
            'password' => 'Temp1234',
            'password_confirmation' => 'Temp1234',
        ], $overrides);
    }

    public function test_admin_can_list_and_filter_staff(): void
    {
        $dept = Department::factory()->create();
        User::factory()->create(['name' => 'Alpha Nurse', 'department_id' => $dept->id])->assignRole('Nurse');
        User::factory()->create(['name' => 'Beta Doctor'])->assignRole('Doctor');

        $this->actingAs($this->administrator())
            ->get(route('admin.users.index', ['role' => 'Nurse']))
            ->assertOk()->assertSee('Alpha Nurse')->assertDontSee('Beta Doctor');

        $this->actingAs($this->administrator())
            ->get(route('admin.users.index', ['q' => 'Beta']))
            ->assertOk()->assertSee('Beta Doctor')->assertDontSee('Alpha Nurse');
    }

    public function test_admin_can_create_staff_with_roles_and_department(): void
    {
        $dept = Department::factory()->create();

        $this->actingAs($this->administrator())
            ->post(route('admin.users.store'), $this->payload(['department_id' => $dept->id, 'roles' => ['Nurse', 'Records Officer']]))
            ->assertRedirect();

        $user = User::where('username', 'grace')->firstOrFail();
        $this->assertTrue($user->hasAllRoles(['Nurse', 'Records Officer']));
        $this->assertSame($dept->id, $user->department_id);
        $this->assertTrue($user->must_change_password);
        $this->assertTrue(Hash::check('Temp1234', $user->password));
    }

    public function test_creating_staff_validates_input(): void
    {
        User::factory()->create(['username' => 'grace']);

        $this->actingAs($this->administrator())
            ->post(route('admin.users.store'), $this->payload(['roles' => [], 'password_confirmation' => 'nope']))
            ->assertSessionHasErrors(['username', 'roles', 'password']);
    }

    public function test_only_super_admin_can_grant_super_admin(): void
    {
        $this->actingAs($this->administrator())
            ->post(route('admin.users.store'), $this->payload(['roles' => ['Super Admin']]))
            ->assertSessionHasErrors('roles.0');

        $this->actingAs($this->superAdmin())
            ->post(route('admin.users.store'), $this->payload(['roles' => ['Super Admin']]))
            ->assertSessionHasNoErrors();
        $this->assertTrue(User::where('username', 'grace')->first()->isSuperAdmin());
    }

    public function test_administrator_cannot_modify_super_admin_account(): void
    {
        $super = $this->superAdmin();
        $admin = $this->administrator();

        $this->actingAs($admin)->get(route('admin.users.edit', $super))->assertForbidden();
        $this->actingAs($admin)->put(route('admin.users.password', $super), ['password' => 'Hijack123', 'password_confirmation' => 'Hijack123'])->assertForbidden();
        $this->actingAs($admin)->patch(route('admin.users.status', $super))->assertForbidden();
    }

    public function test_admin_can_update_staff(): void
    {
        $user = User::factory()->create()->assignRole('Nurse');

        $this->actingAs($this->administrator())
            ->put(route('admin.users.update', $user), $this->payload(['roles' => ['Doctor'], 'designation' => 'Medical Officer']))
            ->assertRedirect(route('admin.users.show', $user));

        $user->refresh();
        $this->assertSame('Medical Officer', $user->designation);
        $this->assertSame(['Doctor'], $user->getRoleNames()->all());
        $this->assertDatabaseHas('audit_logs', ['event' => 'roles_assigned', 'auditable_id' => $user->id]);
    }

    public function test_cannot_deactivate_self(): void
    {
        $admin = $this->administrator();

        $this->actingAs($admin)->patch(route('admin.users.status', $admin))->assertSessionHas('error');
        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_status_toggle_deactivates_and_reactivates(): void
    {
        $user = User::factory()->create();
        $admin = $this->administrator();

        $this->actingAs($admin)->patch(route('admin.users.status', $user));
        $this->assertFalse($user->fresh()->is_active);

        $this->actingAs($admin)->patch(route('admin.users.status', $user));
        $this->assertTrue($user->fresh()->is_active);
    }

    public function test_last_super_admin_is_protected(): void
    {
        $super = $this->superAdmin();
        $other = $this->superAdmin();

        // Two super admins: one may deactivate the other.
        $this->actingAs($super)->patch(route('admin.users.status', $other));
        $this->assertFalse($other->fresh()->is_active);

        // Now $super is the last one and cannot lose the role.
        $this->actingAs($super)
            ->put(route('admin.users.update', $super), $this->payload([
                'username' => $super->username, 'email' => $super->email, 'staff_id' => null, 'roles' => ['Administrator'],
            ]))
            ->assertSessionHasErrors('roles');
        $this->assertTrue($super->fresh()->isSuperAdmin());
    }

    public function test_password_reset_forces_change_at_next_login(): void
    {
        $user = User::factory()->create(['username' => 'nurse1']);

        $this->actingAs($this->administrator())
            ->put(route('admin.users.password', $user), ['password' => 'Reset1234', 'password_confirmation' => 'Reset1234'])
            ->assertSessionHas('success');

        $user->refresh();
        $this->assertTrue($user->must_change_password);
        $this->assertTrue(Hash::check('Reset1234', $user->password));
    }

    public function test_view_only_permission_cannot_manage(): void
    {
        $doctor = User::factory()->create()->assignRole('Doctor'); // has users.view only
        $other = User::factory()->create();

        $this->actingAs($doctor)->get(route('admin.users.index'))->assertOk()->assertDontSee('Add staff');
        $this->actingAs($doctor)->get(route('admin.users.show', $other))->assertOk();
        $this->actingAs($doctor)->get(route('admin.users.create'))->assertForbidden();
        $this->actingAs($doctor)->patch(route('admin.users.status', $other))->assertForbidden();
    }
}
