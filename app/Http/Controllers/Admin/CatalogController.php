<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Clinic;
use App\Models\Drug;
use App\Models\Service;
use App\Models\StoreItem;
use App\Models\SurgicalProcedure;
use App\Models\Theatre;
use App\Models\Vaccine;
use App\Models\ImagingTest;
use App\Models\LabTest;
use App\Models\Prescription;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * One controller for the three clinical catalogues. Items are deactivated,
 * never deleted, because past orders reference them.
 */
class CatalogController extends Controller
{
    public function index(Request $request, string $catalog): View
    {
        $def = $this->definition($catalog);
        $q = trim((string) $request->query('q'));

        $items = $def['model']::query()
            ->when($catalog === 'lab-tests', fn ($query) => $query->withCount('parameters'))
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => collect($def['search'])->each(fn ($col) => $w->orWhere($col, 'like', "%$q%"))))
            ->orderBy($def['group'])->orderBy('name')
            ->paginate(50)->withQueryString();

        return view('admin.catalogs.index', compact('catalog', 'def', 'items', 'q'));
    }

    public function create(string $catalog): View
    {
        $def = $this->definition($catalog);

        return view('admin.catalogs.form', ['catalog' => $catalog, 'def' => $def, 'item' => new $def['model'](['is_active' => true])]);
    }

    public function store(Request $request, string $catalog): RedirectResponse
    {
        $def = $this->definition($catalog);
        $item = $def['model']::create($this->validated($request, $def));

        return redirect()->route('admin.catalogs.index', $catalog)->with('success', "\"{$item->name}\" added.");
    }

    public function edit(string $catalog, int $id): View
    {
        $def = $this->definition($catalog);

        return view('admin.catalogs.form', ['catalog' => $catalog, 'def' => $def, 'item' => $def['model']::findOrFail($id)]);
    }

    public function update(Request $request, string $catalog, int $id): RedirectResponse
    {
        $def = $this->definition($catalog);
        $item = $def['model']::findOrFail($id);
        $item->update($this->validated($request, $def, $item));

        return redirect()->route('admin.catalogs.index', $catalog)->with('success', "\"{$item->name}\" updated.");
    }

    protected function validated(Request $request, array $def, ?Model $item = null): array
    {
        if ($request->has('code')) {
            $request->merge(['code' => Str::upper((string) $request->input('code'))]);
        }

        $rules = ['is_active' => ['boolean']];
        foreach ($def['fields'] as $name => $field) {
            $rules[$name] = array_map(
                fn ($rule) => $rule === 'unique' ? Rule::unique($item?->getTable() ?? (new $def['model'])->getTable())->ignore($item) : $rule,
                $field['rules']
            );
        }

        return $request->validate($rules);
    }

    protected function definition(string $catalog): array
    {
        $definitions = [
            'services' => [
                'title' => 'Billable Services', 'singular' => 'service', 'icon' => 'bi-receipt',
                'model' => Service::class, 'group' => 'category', 'search' => ['name', 'code', 'category'],
                'fields' => [
                    'code' => ['label' => 'Code', 'type' => 'text', 'rules' => ['required', 'alpha_dash', 'max:20', 'unique'], 'col' => 'col-md-3'],
                    'name' => ['label' => 'Service', 'type' => 'text', 'rules' => ['required', 'string', 'max:150'], 'col' => 'col-md-9'],
                    'category' => ['label' => 'Category', 'type' => 'select', 'options' => Service::CATEGORIES, 'rules' => ['required', Rule::in(Service::CATEGORIES)], 'col' => 'col-md-6'],
                    'clinic_id' => ['label' => 'Consultation fee for clinic', 'type' => 'select', 'options' => Clinic::orderBy('name')->pluck('name', 'id')->all(),
                        'rules' => ['nullable', Rule::exists('clinics', 'id')], 'col' => 'col-md-6', 'hidden_in_list' => true],
                ],
            ],
            'procedures' => [
                'title' => 'Surgical Procedures', 'singular' => 'procedure', 'icon' => 'bi-scissors',
                'model' => SurgicalProcedure::class, 'group' => 'specialty', 'search' => ['name', 'code', 'specialty'],
                'fields' => [
                    'code' => ['label' => 'Code', 'type' => 'text', 'rules' => ['required', 'alpha_dash', 'max:20', 'unique'], 'col' => 'col-md-3'],
                    'name' => ['label' => 'Procedure', 'type' => 'text', 'rules' => ['required', 'string', 'max:150', 'unique'], 'col' => 'col-md-9'],
                    'specialty' => ['label' => 'Specialty', 'type' => 'datalist', 'rules' => ['required', 'string', 'max:50'], 'col' => 'col-md-6'],
                    'typical_minutes' => ['label' => 'Typical duration (min)', 'type' => 'number', 'rules' => ['nullable', 'integer', 'min:5', 'max:1440'], 'col' => 'col-md-6'],
                ],
            ],
            'theatres' => [
                'title' => 'Theatres', 'singular' => 'theatre', 'icon' => 'bi-door-closed',
                'model' => Theatre::class, 'group' => 'name', 'search' => ['name', 'code'],
                'fields' => [
                    'code' => ['label' => 'Code', 'type' => 'text', 'rules' => ['required', 'alpha_dash', 'max:10', 'unique'], 'col' => 'col-md-3'],
                    'name' => ['label' => 'Theatre', 'type' => 'text', 'rules' => ['required', 'string', 'max:150', 'unique'], 'col' => 'col-md-9'],
                ],
            ],
            'vaccines' => [
                'title' => 'Vaccine Schedule', 'singular' => 'vaccine dose', 'icon' => 'bi-shield-plus',
                'model' => Vaccine::class, 'group' => 'age_days', 'search' => ['name', 'code'],
                'fields' => [
                    'code' => ['label' => 'Code', 'type' => 'text', 'rules' => ['required', 'alpha_dash', 'max:20', 'unique'], 'col' => 'col-md-3'],
                    'name' => ['label' => 'Vaccine', 'type' => 'datalist', 'rules' => ['required', 'string', 'max:150'], 'col' => 'col-md-5'],
                    'dose_label' => ['label' => 'Dose', 'type' => 'text', 'rules' => ['nullable', 'string', 'max:30'], 'col' => 'col-md-4'],
                    'age_days' => ['label' => 'Due at age (days)', 'type' => 'number', 'rules' => ['required', 'integer', 'min:0', 'max:6000'], 'col' => 'col-md-4'],
                    'route' => ['label' => 'Route', 'type' => 'select', 'options' => ['Oral', 'IM', 'SC', 'Intradermal', 'Intranasal'],
                        'rules' => ['nullable', Rule::in(['Oral', 'IM', 'SC', 'Intradermal', 'Intranasal'])], 'col' => 'col-md-4'],
                    'sort_order' => ['label' => 'Order', 'type' => 'number', 'rules' => ['nullable', 'integer', 'min:0', 'max:1000'], 'col' => 'col-md-4', 'hidden_in_list' => true],
                ],
            ],
            'lab-tests' => [
                'title' => 'Lab Tests', 'singular' => 'lab test', 'icon' => 'bi-droplet-half',
                'model' => LabTest::class, 'group' => 'category', 'search' => ['name', 'code', 'category'],
                'fields' => [
                    'code' => ['label' => 'Code', 'type' => 'text', 'rules' => ['required', 'alpha_dash', 'max:20', 'unique'], 'col' => 'col-md-3'],
                    'name' => ['label' => 'Test name', 'type' => 'text', 'rules' => ['required', 'string', 'max:150', 'unique'], 'col' => 'col-md-9'],
                    'category' => ['label' => 'Category', 'type' => 'datalist', 'rules' => ['required', 'string', 'max:50'], 'col' => 'col-md-4'],
                    'sample_type' => ['label' => 'Sample type', 'type' => 'datalist', 'rules' => ['nullable', 'string', 'max:50'], 'col' => 'col-md-4'],
                    'turnaround_hours' => ['label' => 'Turnaround (hours)', 'type' => 'number', 'rules' => ['nullable', 'integer', 'min:0', 'max:2000'], 'col' => 'col-md-4'],
                ],
            ],
            'imaging' => [
                'title' => 'Imaging Procedures', 'singular' => 'imaging procedure', 'icon' => 'bi-radioactive',
                'model' => ImagingTest::class, 'group' => 'modality', 'search' => ['name', 'code', 'modality'],
                'fields' => [
                    'code' => ['label' => 'Code', 'type' => 'text', 'rules' => ['required', 'alpha_dash', 'max:20', 'unique'], 'col' => 'col-md-3'],
                    'name' => ['label' => 'Procedure', 'type' => 'text', 'rules' => ['required', 'string', 'max:150', 'unique'], 'col' => 'col-md-6'],
                    'modality' => ['label' => 'Modality', 'type' => 'datalist', 'rules' => ['required', 'string', 'max:30'], 'col' => 'col-md-3'],
                    'report_template' => ['label' => 'Normal report template (pre-fills findings)', 'type' => 'textarea', 'rules' => ['nullable', 'string', 'max:5000'], 'col' => 'col-12', 'hidden_in_list' => true],
                ],
            ],
            'drugs' => [
                'title' => 'Drug Formulary', 'singular' => 'drug', 'icon' => 'bi-capsule',
                'model' => Drug::class, 'group' => 'name', 'search' => ['name', 'form'],
                'fields' => [
                    'name' => ['label' => 'Generic name', 'type' => 'text', 'rules' => ['required', 'string', 'max:150'], 'col' => 'col-md-5'],
                    'strength' => ['label' => 'Strength', 'type' => 'text', 'rules' => ['nullable', 'string', 'max:50'], 'col' => 'col-md-3'],
                    'form' => ['label' => 'Form', 'type' => 'datalist', 'rules' => ['required', 'string', 'max:30'], 'col' => 'col-md-4'],
                    'route' => ['label' => 'Default route', 'type' => 'select', 'options' => Prescription::ROUTES, 'rules' => ['nullable', Rule::in(Prescription::ROUTES)], 'col' => 'col-md-4'],
                    'dispensing_unit' => ['label' => 'Stock unit', 'type' => 'datalist', 'rules' => ['nullable', 'string', 'max:30'], 'col' => 'col-md-4', 'hidden_in_list' => true],
                    'reorder_level' => ['label' => 'Reorder level', 'type' => 'number', 'rules' => ['nullable', 'integer', 'min:0', 'max:1000000'], 'col' => 'col-md-4'],
                ],
            ],
            'store-items' => [
                'title' => 'General Store Items', 'singular' => 'store item', 'icon' => 'bi-box-seam', 'permission' => 'stores.manage',
                'model' => StoreItem::class, 'group' => 'category', 'search' => ['name', 'code', 'category'],
                'fields' => [
                    'code' => ['label' => 'Code', 'type' => 'text', 'rules' => ['required', 'alpha_dash', 'max:20', 'unique'], 'col' => 'col-md-3'],
                    'name' => ['label' => 'Item', 'type' => 'text', 'rules' => ['required', 'string', 'max:150'], 'col' => 'col-md-9'],
                    'category' => ['label' => 'Category', 'type' => 'select', 'options' => StoreItem::CATEGORIES, 'rules' => ['required', Rule::in(StoreItem::CATEGORIES)], 'col' => 'col-md-5'],
                    'unit' => ['label' => 'Unit of issue', 'type' => 'datalist', 'rules' => ['required', 'string', 'max:30'], 'col' => 'col-md-4'],
                    'reorder_level' => ['label' => 'Reorder level', 'type' => 'number', 'rules' => ['nullable', 'integer', 'min:0', 'max:1000000'], 'col' => 'col-md-3'],
                ],
            ],
        ];

        abort_unless(isset($definitions[$catalog]), 404);
        abort_unless(auth()->user()->can($definitions[$catalog]['permission'] ?? 'catalog.manage'), 403);

        // Suggestions for datalist fields come from existing values.
        $def = $definitions[$catalog];
        foreach ($def['fields'] as $name => &$field) {
            if ($field['type'] === 'datalist') {
                $field['options'] = $def['model']::query()->whereNotNull($name)->distinct()->orderBy($name)->pluck($name)->all();
            }
        }

        return $def;
    }
}
