<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DepartmentController extends Controller
{
    public function index(Request $request): View
    {
        $departments = Department::with('head')
            ->withCount('users')
            ->when($request->query('q'), fn ($q, $v) => $q->where(fn ($q) => $q->where('name', 'like', "%$v%")->orWhere('code', 'like', "%$v%")))
            ->when($request->query('type'), fn ($q, $v) => $q->where('type', $v))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('admin.departments.index', compact('departments'));
    }

    public function create(): View
    {
        return view('admin.departments.create', ['department' => new Department(['is_active' => true]), 'heads' => $this->heads()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $department = Department::create($this->validated($request));

        return redirect()->route('admin.departments.index')->with('success', "Department \"{$department->name}\" created.");
    }

    public function edit(Department $department): View
    {
        return view('admin.departments.edit', ['department' => $department, 'heads' => $this->heads()]);
    }

    public function update(Request $request, Department $department): RedirectResponse
    {
        $department->update($this->validated($request, $department));

        return redirect()->route('admin.departments.index')->with('success', "Department \"{$department->name}\" updated.");
    }

    public function destroy(Department $department): RedirectResponse
    {
        if ($department->users()->exists()) {
            return back()->with('error', "\"{$department->name}\" still has staff assigned. Move them or deactivate the department instead.");
        }

        $department->delete();

        return redirect()->route('admin.departments.index')->with('success', "Department \"{$department->name}\" deleted.");
    }

    protected function validated(Request $request, ?Department $department = null): array
    {
        $request->merge(['code' => Str::upper((string) $request->input('code'))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('departments')->ignore($department)],
            'code' => ['required', 'alpha_dash', 'max:20', Rule::unique('departments')->ignore($department)],
            'type' => ['required', Rule::in(config('emr.department_types'))],
            'description' => ['nullable', 'string', 'max:1000'],
            'location' => ['nullable', 'string', 'max:150'],
            'phone_extension' => ['nullable', 'string', 'max:20'],
            'head_id' => ['nullable', Rule::exists('users', 'id')->where('is_active', true)],
            'is_active' => ['boolean'],
        ]);

        return $data + ['is_active' => false];
    }

    protected function heads()
    {
        return User::active()->orderBy('name')->get(['id', 'name', 'designation']);
    }
}
