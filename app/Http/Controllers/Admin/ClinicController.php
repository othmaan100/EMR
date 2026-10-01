<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Clinic;
use App\Models\Department;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ClinicController extends Controller
{
    public function index(): View
    {
        return view('admin.clinics.index', [
            'clinics' => Clinic::with('department')->withCount('visits')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.clinics.create', $this->formData(new Clinic(['is_active' => true, 'requires_triage' => true])));
    }

    public function store(Request $request): RedirectResponse
    {
        $clinic = Clinic::create($this->validated($request));

        return redirect()->route('admin.clinics.index')->with('success', "Clinic \"{$clinic->name}\" created.");
    }

    public function edit(Clinic $clinic): View
    {
        return view('admin.clinics.edit', $this->formData($clinic));
    }

    public function update(Request $request, Clinic $clinic): RedirectResponse
    {
        $clinic->update($this->validated($request, $clinic));

        return redirect()->route('admin.clinics.index')->with('success', "Clinic \"{$clinic->name}\" updated.");
    }

    public function destroy(Clinic $clinic): RedirectResponse
    {
        if ($clinic->visits()->exists() || $clinic->appointments()->exists()) {
            return back()->with('error', "\"{$clinic->name}\" has visit or appointment history. Deactivate it instead.");
        }

        $clinic->delete();

        return redirect()->route('admin.clinics.index')->with('success', "Clinic \"{$clinic->name}\" deleted.");
    }

    protected function validated(Request $request, ?Clinic $clinic = null): array
    {
        $request->merge(['code' => Str::upper((string) $request->input('code'))]);

        return $request->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('clinics')->ignore($clinic)],
            'code' => ['required', 'alpha_dash', 'max:10', Rule::unique('clinics')->ignore($clinic)],
            'department_id' => ['nullable', Rule::exists('departments', 'id')],
            'location' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'requires_triage' => ['boolean'],
            'specialty' => ['nullable', Rule::in(array_keys(config('emr.specialty.clinic_types')))],
            'is_active' => ['boolean'],
        ]) + ['specialty' => $request->input('specialty') ?: 'general'];
    }

    protected function formData(Clinic $clinic): array
    {
        return [
            'clinic' => $clinic,
            'departments' => Department::active()->orWhere('id', $clinic->department_id)->orderBy('name')->pluck('name', 'id'),
        ];
    }
}
