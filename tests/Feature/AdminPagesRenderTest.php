<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Smoke test: every admin screen renders for a Super Admin.
 */
class AdminPagesRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_admin_pages_render(): void
    {
        $this->markInstalled();
        $admin = $this->superAdmin();
        $dept = Department::factory()->create(['head_id' => $admin->id]);
        $staff = User::factory()->create(['department_id' => $dept->id])->assignRole('Nurse');
        $staff->update(['name' => 'Changed']);

        $pages = [
            route('dashboard'),
            route('account.profile'),
            route('admin.users.index'),
            route('admin.users.create'),
            route('admin.users.show', $staff),
            route('admin.users.edit', $staff),
            route('admin.departments.index'),
            route('admin.departments.create'),
            route('admin.departments.edit', $dept),
            route('admin.roles.index'),
            route('admin.roles.create'),
            route('admin.roles.edit', Role::findByName('Doctor')),
            route('admin.settings.edit', ['section' => 'profile']),
            route('admin.settings.edit', ['section' => 'contact']),
            route('admin.settings.edit', ['section' => 'preferences']),
            route('admin.audit.index'),
            route('admin.audit.show', AuditLog::first()),
        ];

        foreach ($pages as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }
}
