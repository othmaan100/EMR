<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Imports\Importer;
use App\Imports\ImportRegistry;
use App\Imports\SpreadsheetFile;
use App\Models\DataImport;
use App\Services\ImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Administration → Data Import: bring records over from a legacy system.
 */
class ImportController extends Controller
{
    public function __construct(protected ImportService $imports) {}

    public function index(): View
    {
        $importers = ImportRegistry::all();
        $done = DataImport::where('status', 'completed')->selectRaw('type, count(*) as runs, sum(created_count) as created, max(completed_at) as last')
            ->groupBy('type')->get()->keyBy('type');

        return view('admin.imports.index', [
            'groups' => $importers->groupBy(fn (Importer $i) => $i->group()),
            'done' => $done,
            'recent' => DataImport::with('user')->latest('id')->limit(10)->get(),
        ]);
    }

    public function show(string $type): View
    {
        $importer = $this->importer($type);

        return view('admin.imports.show', [
            'importer' => $importer,
            'step' => ImportRegistry::step($type),
            'dependencies' => collect($importer->dependsOn())->map(fn ($k) => ImportRegistry::find($k))->filter(),
            'previous' => DataImport::with('user')->where('type', $type)->latest('id')->limit(10)->get(),
        ]);
    }

    public function template(Request $request, string $type): Response|BinaryFileResponse
    {
        $importer = $this->importer($type);
        $samples = $request->boolean('sample');
        $name = Str::slug($importer->title()).($samples ? '-sample' : '-template');

        if ($request->query('format') === 'csv') {
            return response(SpreadsheetFile::writeCsvTemplate($importer, $samples), 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"{$name}.csv\"",
            ]);
        }

        $path = tempnam(sys_get_temp_dir(), 'tpl').'.xlsx';
        SpreadsheetFile::writeTemplate($importer, $path, $samples);

        return response()->download($path, "{$name}.xlsx")->deleteFileAfterSend();
    }

    public function upload(Request $request, string $type): RedirectResponse
    {
        $importer = $this->importer($type);

        $rules = [
            'file' => ['required', 'file', 'max:20480', 'mimes:csv,txt,xlsx'],
            'mode' => ['required', 'in:create,update'],
        ];
        foreach ($importer->options() as $name => [$label, , $optionRules]) {
            $rules["options.{$name}"] = $optionRules;
        }
        $data = $request->validate($rules, ['file.mimes' => 'Upload an Excel (.xlsx) or CSV file.', 'file.max' => 'The file may not be larger than 20 MB.']);

        $options = $importer::prepareOptions($data['options'] ?? []);
        $import = $this->imports->analyse($importer, $request->file('file')->getRealPath(), $request->file('file')->getClientOriginalName(),
            $data['mode'], $options, $request->user());

        return redirect()->route('admin.imports.review', $import);
    }

    public function review(DataImport $import): View
    {
        $importer = $this->importer($import->type);

        return view('admin.imports.review', ['import' => $import->load('user'), 'importer' => $importer]);
    }

    public function run(Request $request, DataImport $import): RedirectResponse
    {
        $this->importer($import->type);
        if ($import->status !== 'validated') {
            return back()->with('error', 'This import has already been run or cancelled.');
        }

        $import = $this->imports->run($import, $request->user());

        return redirect()->route('admin.imports.review', $import)->with('success',
            "Import finished: {$import->created_count} added, {$import->updated_count} updated, {$import->skipped_count} skipped.");
    }

    public function cancel(DataImport $import): RedirectResponse
    {
        $this->importer($import->type);
        $this->imports->cancel($import);

        return redirect()->route('admin.imports.show', $import->type)->with('success', 'Import cancelled; nothing was saved.');
    }

    public function errors(DataImport $import): Response
    {
        $this->importer($import->type);
        abort_if(empty($import->errors), 404);

        return response($this->imports->errorReport($import), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.Str::slug($import->typeTitle()).'-errors-'.$import->id.'.csv"',
        ]);
    }

    public function history(): View
    {
        return view('admin.imports.history', ['imports' => DataImport::with('user')->latest('id')->paginate(30)]);
    }

    protected function importer(string $type): Importer
    {
        $importer = ImportRegistry::make($type);
        if ($importer->permission()) {
            abort_unless(auth()->user()->can($importer->permission()), 403);
        }

        return $importer;
    }
}
