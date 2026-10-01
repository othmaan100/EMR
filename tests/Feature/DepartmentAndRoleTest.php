<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DepartmentAndRoleTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->markInstalled();
        $this->admin = User::factory()->create()->assignRole('Administrator');
    }

    public function test_department_crud(): void
    {
        $this->actingAs($this->admin)->post(route('admin.departments.store'), [
            'name' => 'Outpatient Department', 'code' => 'opd', 'type' => 'Clinical', 'head_id' => $this->admin->id, 'is_active' => 1,
        ])->assertRedirect(route('admin.departments.index'));

        $dept = Department::where('code', 'OPD')->firstOrFail();
        $this->assertSame($this->admin->id, $dept->head_id);

        $this->actingAs($this->admin)->get(route('admin.departments.index'))->assertOk()->assertSee('Outpatient Department');

        $this->actingAs($this->admin)->put(route('admin.departments.update', $dept), [
            'name' => 'OPD Clinic', 'code' => 'OPD', 'type' => 'Clinical', 'is_active' => 0,
        ])->assertRedirect();
        $this->assertFalse($dept->fresh()->is_active);
        $this->assertSame('OPD Clinic', $dept->fresh()->name);

        $this->actingAs($this->admin)->delete(route('admin.departments.destroy', $dept));
        $this->assertModelMissing($dept);
    }

    public function test_department_code_must_be_unique(): void
    {
        Department::factory()->create(['code' => 'LAB']);

        $this->actingAs($this->admin)->post(route('admin.departments.store'), [
            'name' => 'Laboratory', 'code' => 'lab', 'type' => 'Diagnostic',
        ])->assertSessionHasErrors('code');
    }

    public function test_department_with_staff_cannot_be_deleted(): void
    {
        $dept = Department::factory()->create();
        User::factory()->create(['department_id' => $dept->id]);

        $this->actingAs($this->admin)->delete(route('admin.departments.destroy', $dept))->assertSessionHas('error');
        $this->assertModelExists($dept);
    }

    public function test_role_permissions_can_be_edited_and_take_effect(): void
    {
        $nurse = User::factory()->create()->assignRole('Nurse');
        $this->actingAs($nurse)->get(route('admin.departments.index'))->assertForbidden();

        $role = Role::findByName('Nurse');
        $this->actingAs($this->admin)->put(route('admin.roles.update', $role), [
            'name' => 'Renamed', // ignored for built-in roles
            'permissions' => ['users.view', 'departments.manage'],
        ])->assertRedirect(route('admin.roles.index'));

        $role->refresh();
        $this->assertSame('Nurse', $role->name);
        $this->assertTrue($role->hasPermissionTo('departments.manage'));
        $this->assertDatabaseHas('audit_logs', ['event' => 'role_updated']);

        $this->actingAs($nurse->fresh())->get(route('admin.departments.index'))->assertOk();
    }

    public function test_custom_role_lifecycle(): void
    {
        $this->actingAs($this->admin)->post(route('admin.roles.store'), [
            'name' => 'Speech Therapist', 'permissions' => ['users.view'],
        ])->assertRedirect();

        $role = Role::findByName('Speech Therapist');
        $this->assertTrue($role->hasPermissionTo('users.view'));

        $user = User::factory()->create()->assignRole($role);
        $this->actingAs($this->admin)->delete(route('admin.roles.destroy', $role))->assertSessionHas('error');

        $user->removeRole($role);
        $this->actingAs($this->admin)->delete(route('admin.roles.destroy', $role))->assertSessionHas('success');
        $this->assertNull(Role::where('name', 'Speech Therapist')->first());
    }

    public function test_built_in_and_super_admin_roles_are_protected(): void
    {
        $this->actingAs($this->admin)->delete(route('admin.roles.destroy', Role::findByName('Doctor')))->assertSessionHas('error');
        $this->actingAs($this->admin)->get(route('admin.roles.edit', Role::findByName('Super Admin')))->assertForbidden();
    }

    public function test_invalid_permission_is_rejected(): void
    {
        $this->actingAs($this->admin)->post(route('admin.roles.store'), [
            'name' => 'Hacker', 'permissions' => ['everything.forever'],
        ])->assertSessionHasErrors('permissions.0');
    }
}
