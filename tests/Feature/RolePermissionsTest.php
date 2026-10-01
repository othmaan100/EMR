<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Guards the built-in role definitions against accidental grants
 * (e.g. a finance permission slipping into a clinical role).
 */
class RolePermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_roles_match_config_exactly(): void
    {
        $this->markInstalled();

        foreach (config('emr.roles') as $name => $permissions) {
            $this->assertEqualsCanonicalizing(
                $permissions,
                Role::findByName($name)->permissions->pluck('name')->all(),
                "Role {$name} permissions differ from config/emr.php"
            );
        }
    }

    public function test_role_definitions_have_no_duplicates_or_unknown_permissions(): void
    {
        $known = collect(config('emr.permissions'))->flatMap(fn ($group) => array_keys($group))->all();

        foreach (config('emr.roles') as $name => $permissions) {
            $this->assertSame(count($permissions), count(array_unique($permissions)), "{$name} lists a permission twice");
            $this->assertEmpty(array_diff($permissions, $known), "{$name} has undefined permissions");
        }
    }

    public function test_clinical_and_support_roles_have_no_finance_powers(): void
    {
        $finance = ['billing.discount', 'billing.reverse', 'billing.prices', 'billing.claims'];

        foreach (['Doctor', 'Nurse', 'Pharmacist', 'Lab Scientist', 'Radiologist', 'Radiographer', 'Records Officer', 'Cashier'] as $role) {
            $this->assertEmpty(array_intersect($finance, config("emr.roles.$role")), "{$role} must not manage prices, discounts, reversals or claims");
        }
        foreach (['Pharmacist', 'Lab Scientist', 'Radiologist', 'Radiographer', 'Cashier', 'Accountant'] as $role) {
            $this->assertNotContains('wards.manage', config("emr.roles.$role"));
            $this->assertNotContains('admissions.discharge', config("emr.roles.$role"));
        }
    }
}
