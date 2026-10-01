<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bed;
use App\Models\Department;
use App\Models\Service;
use App\Models\Ward;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WardController extends Controller
{
    public function index(): View
    {
        return view('admin.wards.index', [
            'wards' => Ward::with(['bedCharge.prices', 'department'])->withCount(['beds', 'beds as occupied_count' => fn ($q) => $q->where('status', 'occupied')])
                ->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.wards.form', $this->formData(new Ward(['is_active' => true, 'gender' => 'any'])));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $ward = Ward::create($data);
        $this->addBeds($ward, (int) $request->input('bed_count', 0), $request->input('bed_prefix', ''));

        return redirect()->route('admin.wards.edit', $ward)->with('success', "Ward \"{$ward->name}\" created.");
    }

    public function edit(Ward $ward): View
    {
        return view('admin.wards.form', $this->formData($ward->load('beds.currentAdmission.patient')));
    }

    public function update(Request $request, Ward $ward): RedirectResponse
    {
        $ward->update($this->validated($request, $ward));

        return back()->with('success', 'Ward updated.');
    }

    public function addBedsAction(Request $request, Ward $ward): RedirectResponse
    {
        $data = $request->validate([
            'bed_count' => ['required', 'integer', 'min:1', 'max:100'],
            'bed_prefix' => ['nullable', 'string', 'max:10'],
        ]);
        $added = $this->addBeds($ward, $data['bed_count'], $data['bed_prefix'] ?? '');

        return back()->with('success', "{$added} bed(s) added.");
    }

    public function bedStatus(Request $request, Bed $bed): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['available', 'cleaning', 'out_of_service'])]]);
        abort_if($bed->status === 'occupied', 422, 'An occupied bed cannot be changed; transfer or discharge the patient first.');

        $bed->update($data);

        return back()->with('success', "Bed {$bed->label} marked ".Bed::STATUSES[$data['status']]['label'].'.');
    }

    /**
     * Create beds numbered after the existing ones (e.g. A1, A2 …).
     */
    protected function addBeds(Ward $ward, int $count, string $prefix): int
    {
        $prefix = Str::upper(trim($prefix));
        $existing = $ward->beds()->pluck('label')->all();
        $n = 1;
        for ($i = 0; $i < $count; $i++) {
            while (in_array($prefix.$n, $existing, true)) {
                $n++;
            }
            $ward->beds()->create(['label' => $prefix.$n]);
            $existing[] = $prefix.$n;
        }

        return $count;
    }

    protected function validated(Request $request, ?Ward $ward = null): array
    {
        $request->merge(['code' => Str::upper((string) $request->input('code'))]);

        return $request->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('wards')->ignore($ward)],
            'code' => ['required', 'alpha_dash', 'max:10', Rule::unique('wards')->ignore($ward)],
            'type' => ['required', Rule::in(Ward::TYPES)],
            'gender' => ['required', Rule::in(['any', 'male', 'female'])],
            'department_id' => ['nullable', Rule::exists('departments', 'id')],
            'service_id' => ['nullable', Rule::exists('services', 'id')],
            'is_active' => ['boolean'],
            'bed_count' => ['nullable', 'integer', 'min:0', 'max:100'],
            'bed_prefix' => ['nullable', 'string', 'max:10'],
        ]) + ['is_active' => false];
    }

    protected function formData(Ward $ward): array
    {
        return [
            'ward' => $ward,
            'departments' => Department::orderBy('name')->pluck('name', 'id'),
            'services' => Service::active()->where('category', 'Accommodation')->orderBy('name')->pluck('name', 'id'),
        ];
    }
}
