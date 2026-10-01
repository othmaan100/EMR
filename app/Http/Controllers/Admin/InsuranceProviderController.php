<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InsuranceProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InsuranceProviderController extends Controller
{
    public function index(): View
    {
        return view('admin.insurance.index', [
            'providers' => InsuranceProvider::withCount('patients')->orderBy('name')->paginate(25),
        ]);
    }

    public function create(): View
    {
        return view('admin.insurance.create', ['provider' => new InsuranceProvider(['is_active' => true, 'type' => 'insurance'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $provider = InsuranceProvider::create($this->validated($request));

        return redirect()->route('admin.insurance.index')->with('success', "\"{$provider->name}\" added.");
    }

    public function edit(InsuranceProvider $insurance): View
    {
        return view('admin.insurance.edit', ['provider' => $insurance]);
    }

    public function update(Request $request, InsuranceProvider $insurance): RedirectResponse
    {
        $insurance->update($this->validated($request, $insurance));

        return redirect()->route('admin.insurance.index')->with('success', "\"{$insurance->name}\" updated.");
    }

    public function destroy(InsuranceProvider $insurance): RedirectResponse
    {
        if ($insurance->patients()->exists()) {
            return back()->with('error', "\"{$insurance->name}\" has enrolled patients. Deactivate it instead.");
        }

        $insurance->delete();

        return redirect()->route('admin.insurance.index')->with('success', "\"{$insurance->name}\" deleted.");
    }

    protected function validated(Request $request, ?InsuranceProvider $provider = null): array
    {
        $request->merge(['code' => Str::upper((string) $request->input('code'))]);

        $request->mergeIfMissing(['coverage_percent' => 100]);

        return $request->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('insurance_providers')->ignore($provider)],
            'code' => ['required', 'alpha_dash', 'max:20', Rule::unique('insurance_providers')->ignore($provider)],
            'type' => ['required', Rule::in(['insurance', 'corporate'])],
            'coverage_percent' => ['required', 'integer', 'between:0,100'],
            'contact_person' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
            'requires_authorization' => ['boolean'],
        ]);
    }
}
