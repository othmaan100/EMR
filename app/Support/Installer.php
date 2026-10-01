<?php

namespace App\Support;

use Database\Seeders\CatalogSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Throwable;

class Installer
{
    public const REQUIRED_EXTENSIONS = ['pdo_mysql', 'mbstring', 'openssl', 'fileinfo', 'gd', 'curl', 'intl', 'zip'];

    public function isInstalled(): bool
    {
        return file_exists(config('emr.installed_file'));
    }

    public function markInstalled(): void
    {
        file_put_contents(config('emr.installed_file'), now()->toIso8601String());
    }

    /**
     * @return list<array{label: string, ok: bool, detail: string}>
     */
    public function requirements(): array
    {
        $checks = [[
            'label' => 'PHP version 8.2 or higher',
            'ok' => version_compare(PHP_VERSION, '8.2.0', '>='),
            'detail' => PHP_VERSION,
        ]];

        foreach (self::REQUIRED_EXTENSIONS as $ext) {
            $checks[] = [
                'label' => "PHP extension: $ext",
                'ok' => extension_loaded($ext),
                'detail' => extension_loaded($ext) ? 'Loaded' : 'Missing',
            ];
        }

        foreach (['storage' => storage_path(), 'bootstrap/cache' => base_path('bootstrap/cache'), 'public' => public_path()] as $label => $path) {
            $checks[] = [
                'label' => "Writable folder: $label",
                'ok' => is_writable($path),
                'detail' => is_writable($path) ? 'Writable' : 'Not writable',
            ];
        }

        $db = $this->databaseStatus();
        $checks[] = ['label' => 'Database connection', 'ok' => $db['ok'], 'detail' => $db['detail']];

        return $checks;
    }

    /**
     * @return array{ok: bool, detail: string}
     */
    public function databaseStatus(): array
    {
        try {
            DB::connection()->getPdo();

            return ['ok' => true, 'detail' => 'Connected to "'.DB::connection()->getDatabaseName().'"'];
        } catch (Throwable $e) {
            return ['ok' => false, 'detail' => 'Check DB_* values in .env — '.$e->getMessage()];
        }
    }

    public function requirementsMet(): bool
    {
        return collect($this->requirements())->every('ok');
    }

    /**
     * Create/upgrade database tables and seed default roles & permissions.
     */
    public function prepareDatabase(): void
    {
        Artisan::call('migrate', ['--force' => true]);
        app(RolesAndPermissionsSeeder::class)->run();
        app(CatalogSeeder::class)->run();
        app(Settings::class)->flush();
    }
}
