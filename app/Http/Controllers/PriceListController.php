<?php

namespace App\Http\Controllers;

use App\Models\Drug;
use App\Models\ImagingTest;
use App\Models\InsuranceProvider;
use App\Models\LabTest;
use App\Models\Price;
use App\Models\Service;
use App\Models\SurgicalProcedure;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Default (self-pay) prices plus optional insurer/HMO-specific prices.
 */
class PriceListController extends Controller
{
    public const TYPES = [
        'services' => ['label' => 'Services', 'model' => Service::class, 'group' => 'category'],
        'lab-tests' => ['label' => 'Lab tests', 'model' => LabTest::class, 'group' => 'category'],
        'imaging' => ['label' => 'Imaging', 'model' => ImagingTest::class, 'group' => 'modality'],
        'drugs' => ['label' => 'Drugs (per unit)', 'model' => Drug::class, 'group' => null],
        'procedures' => ['label' => 'Surgery', 'model' => SurgicalProcedure::class, 'group' => 'specialty'],
    ];

    public function index(Request $request): View
    {
        $type = array_key_exists($request->query('type'), self::TYPES) ? $request->query('type') : 'services';
        $def = self::TYPES[$type];
        $provider = $request->integer('provider_id') ? InsuranceProvider::find($request->integer('provider_id')) : null;
        $q = trim((string) $request->query('q'));

        $items = $def['model']::query()->where('is_active', true)->with('prices')
            ->when($q !== '', fn ($query) => $query->where('name', 'like', "%$q%"))
            ->when($def['group'], fn ($query) => $query->orderBy($def['group']))
            ->orderBy('name')->get();

        return view('billing.prices', [
            'type' => $type,
            'def' => $def,
            'items' => $items,
            'provider' => $provider,
            'providers' => InsuranceProvider::active()->orderBy('name')->pluck('name', 'id'),
            'q' => $q,
            'unpriced' => collect(self::TYPES)->map(fn ($t) => $t['model']::where('is_active', true)
                ->whereDoesntHave('prices', fn ($p) => $p->whereNull('insurance_provider_id'))->count()),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $type = $request->input('type');
        abort_unless(array_key_exists($type, self::TYPES), 404);

        $data = $request->validate([
            'provider_id' => ['nullable', 'exists:insurance_providers,id'],
            'prices' => ['array'],
            'prices.*' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
        ]);

        $model = self::TYPES[$type]['model'];
        $providerId = $data['provider_id'] ?? null;
        $changed = 0;

        DB::transaction(function () use ($data, $model, $providerId, &$changed) {
            foreach ($data['prices'] ?? [] as $id => $amount) {
                $match = ['billable_type' => (new $model)->getMorphClass(), 'billable_id' => (int) $id, 'insurance_provider_id' => $providerId];
                $existing = Price::where($match)->first();

                if ($amount === null || $amount === '') {
                    if ($existing) {
                        $existing->delete();
                        $changed++;
                    }
                } elseif (! $existing || (float) $existing->amount !== (float) $amount) {
                    Price::updateOrCreate($match, ['amount' => $amount]);
                    $changed++;
                }
            }
        });

        if ($changed) {
            Audit::log('prices_updated', "{$changed} price(s) changed in ".self::TYPES[$type]['label'].($providerId ? ' for '.InsuranceProvider::find($providerId)->name : ' (default)'));
        }

        return back()->with('success', $changed ? "{$changed} price(s) saved." : 'No changes.');
    }
}
