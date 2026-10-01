<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LabTest;
use App\Models\LabTestParameter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Result fields and reference ranges of a lab test.
 * Existing results keep a snapshot, so edits never alter past reports.
 */
class LabParameterController extends Controller
{
    public function index(LabTest $labTest): View
    {
        return view('admin.catalogs.parameters', ['test' => $labTest->load('parameters')]);
    }

    public function store(Request $request, LabTest $labTest): RedirectResponse
    {
        $labTest->parameters()->create($this->validated($request) + ['sort_order' => $labTest->parameters()->max('sort_order') + 1]);

        return back()->with('success', 'Result field added.');
    }

    public function update(Request $request, LabTest $labTest, LabTestParameter $parameter): RedirectResponse
    {
        abort_unless($parameter->lab_test_id === $labTest->id, 404);
        $parameter->update($this->validated($request));

        return back()->with('success', "\"{$parameter->name}\" updated.");
    }

    public function destroy(LabTest $labTest, LabTestParameter $parameter): RedirectResponse
    {
        abort_unless($parameter->lab_test_id === $labTest->id, 404);
        $parameter->delete();

        return back()->with('success', 'Result field removed.');
    }

    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'unit' => ['nullable', 'string', 'max:30'],
            'type' => ['required', Rule::in(array_keys(LabTestParameter::TYPES))],
            'options' => ['nullable', 'required_if:type,option', 'string', 'max:500'],
            'ref_low' => ['nullable', 'numeric'],
            'ref_high' => ['nullable', 'numeric'],
            'ref_text' => ['nullable', 'string', 'max:50'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ], ['options.required_if' => 'List the choices, separated by commas.']);

        if (isset($data['ref_low'], $data['ref_high']) && $data['ref_high'] < $data['ref_low']) {
            throw ValidationException::withMessages(['ref_high' => 'The upper limit must not be below the lower limit.']);
        }

        $data['options'] = $data['type'] === 'option'
            ? array_values(array_filter(array_map('trim', explode(',', $data['options'] ?? ''))))
            : null;
        if ($data['type'] !== 'numeric') {
            $data['ref_low'] = $data['ref_high'] = null;
        }
        $data['sort_order'] ??= 0;

        return $data;
    }
}
