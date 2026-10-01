<?php

namespace Tests;

use App\Models\User;
use App\Support\Installer;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        @unlink(config('emr.installed_file'));
    }

    protected function tearDown(): void
    {
        @unlink(config('emr.installed_file'));

        parent::tearDown();
    }

    /**
     * Put the app in the "setup completed" state.
     */
    protected function markInstalled(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        app(Installer::class)->markInstalled();
    }

    protected function superAdmin(): User
    {
        return User::factory()->create()->assignRole(config('emr.super_admin_role'));
    }
}
