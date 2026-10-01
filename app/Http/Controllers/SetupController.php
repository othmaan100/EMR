<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Audit;
use App\Support\HospitalProfile;
use App\Support\Installer;
use App\Support\Settings;
use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Throwable;

/**
 * First-run setup wizard. Each step must be completed in order; progress is
 * tracked in the session. Access is blocked by EnsureInstalled once done.
 */
class SetupController extends Controller
{
    public const STEPS = [
        'requirements' => ['title' => 'System Check', 'icon' => 'bi-cpu'],
        'profile' => ['title' => 'Hospital Profile', 'icon' => 'bi-hospital'],
        'contact' => ['title' => 'Contact & Address', 'icon' => 'bi-geo-alt'],
        'preferences' => ['title' => 'Preferences', 'icon' => 'bi-sliders'],
        'admin' => ['title' => 'Administrator', 'icon' => 'bi-person-badge'],
    ];

    public function __construct(
        protected Installer $installer,
        protected HospitalProfile $profile,
    ) {}

    public function index(): RedirectResponse
    {
        return redirect()->route('setup.step', $this->nextStep());
    }

    public function show(string $step): View|RedirectResponse
    {
        abort_unless(isset(self::STEPS[$step]), 404);

        if (! $this->canAccess($step)) {
            return redirect()->route('setup.step', $this->nextStep());
        }

        return view("setup.$step", [
            'step' => $step,
            'steps' => self::STEPS,
            'completed' => session('setup.completed', []),
            'requirements' => $step === 'requirements' ? $this->installer->requirements() : [],
            'timezones' => $step === 'preferences' ? DateTimeZone::listIdentifiers() : [],
        ]);
    }

    public function store(Request $request, string $step): RedirectResponse
    {
        abort_unless(isset(self::STEPS[$step]), 404);

        if (! $this->canAccess($step)) {
            return redirect()->route('setup.step', $this->nextStep());
        }

        if ($step === 'requirements') {
            return $this->storeRequirements();
        }

        if ($step === 'admin') {
            return $this->storeAdmin($request);
        }

        $this->profile->save($request, $step);
        $this->complete($step);

        return redirect()->route('setup.step', $this->nextStep());
    }

    protected function storeRequirements(): RedirectResponse
    {
        if (! $this->installer->requirementsMet()) {
            return back()->with('error', 'Please resolve the failed checks before continuing.');
        }

        try {
            $this->installer->prepareDatabase();
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', 'Database setup failed: '.$e->getMessage());
        }

        $this->complete('requirements');

        return redirect()->route('setup.step', 'profile');
    }

    protected function storeAdmin(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'username' => ['required', 'alpha_dash', 'min:3', 'max:50', 'unique:users,username'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = DB::transaction(function () use ($data) {
            $user = User::create($data + ['designation' => 'System Administrator', 'is_active' => true]);
            $user->assignRole(config('emr.super_admin_role'));

            return $user;
        });

        app(Settings::class)->set('installed_at', now()->toDateTimeString());
        $this->installer->markInstalled();
        Audit::log('installed', 'Setup wizard completed', $user, userId: $user->id);

        session()->forget('setup');
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')
            ->with('success', 'Setup complete! Welcome to '.setting('hospital_name').'.');
    }

    protected function complete(string $step): void
    {
        $completed = session('setup.completed', []);
        $completed[] = $step;
        session(['setup.completed' => array_values(array_unique($completed))]);
    }

    protected function nextStep(): string
    {
        $completed = session('setup.completed', []);

        foreach (array_keys(self::STEPS) as $step) {
            if (! in_array($step, $completed, true)) {
                return $step;
            }
        }

        return 'admin';
    }

    /**
     * A step is reachable if every step before it has been completed.
     */
    protected function canAccess(string $step): bool
    {
        $completed = session('setup.completed', []);

        foreach (array_keys(self::STEPS) as $key) {
            if ($key === $step) {
                return true;
            }
            if (! in_array($key, $completed, true)) {
                return false;
            }
        }

        return false;
    }
}
