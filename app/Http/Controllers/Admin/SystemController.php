<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\BackupService;
use App\Support\Audit;
use App\Support\SystemHealth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SystemController extends Controller
{
    public function __construct(protected BackupService $backups) {}

    public function index(SystemHealth $health): View
    {
        Cache::forget('emr.health.failures'); // refresh the dashboard banner too

        return view('admin.system.index', [
            'checks' => $health->checks(),
            'backups' => $this->backups->list(),
            'directory' => $this->backups->directory(),
        ]);
    }

    public function backup(): RedirectResponse
    {
        try {
            $result = $this->backups->create();
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Backup failed: '.$e->getMessage());
        }

        return back()->with('success', "Backup {$result['name']} created ({$this->backups->human($result['size'])}).");
    }

    /**
     * A backup contains every patient record: Super Admin only.
     */
    public function download(Request $request, string $name): BinaryFileResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403, 'Only a Super Admin can download backups.');

        $path = $this->backups->path($name);
        Audit::log('backup_downloaded', "Backup {$name} downloaded");

        return response()->download($path);
    }
}
