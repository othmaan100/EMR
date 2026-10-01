<?php

namespace App\Http\Controllers;

use App\Models\Clinic;
use App\Models\Ward;
use App\Services\ReportService;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(protected ReportService $reports) {}

    public function index(Request $request): View
    {
        [$from, $to] = $this->range($request);

        return view('reports.index', [
            'groups' => collect(ReportService::REPORTS)->filter(fn ($r) => $request->user()->can($r[2]))->groupBy(fn ($r) => $r[1], true),
            'overview' => $this->reports->overview($from, $to),
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function show(Request $request, string $report): View|StreamedResponse
    {
        $def = ReportService::REPORTS[$report] ?? abort(404);
        abort_unless($request->user()->can($def[2]), 403);

        [$from, $to] = $this->range($request);
        $filters = $request->only(['clinic_id', 'ward_id']);
        $result = $this->reports->run($report, $from, $to, $filters);

        if ($request->query('export') === 'csv') {
            Audit::log('report_exported', "Exported \"{$def[0]}\" ({$from->toDateString()} to {$to->toDateString()})");

            return $this->csv($def[0], $from, $to, $result);
        }

        return view('reports.show', [
            'key' => $report,
            'def' => $def,
            'result' => $result,
            'from' => $from,
            'to' => $to,
            'filters' => $filters,
            'clinics' => in_array('clinic', $def[4], true) ? Clinic::orderBy('name')->pluck('name', 'id') : collect(),
            'wards' => in_array('ward', $def[4], true) ? Ward::orderBy('name')->pluck('name', 'id') : collect(),
        ]);
    }

    /**
     * Default: this month to date. Capped at 366 days.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function range(Request $request): array
    {
        $from = rescue(fn () => Carbon::parse($request->query('from')), null, false) ?? today()->startOfMonth();
        $to = rescue(fn () => Carbon::parse($request->query('to')), null, false) ?? today();

        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }
        if ($from->diffInDays($to) > 366) {
            $from = $to->copy()->subDays(366);
        }

        return [$from->startOfDay(), $to->startOfDay()];
    }

    /**
     * Neutralise spreadsheet formulas in text (CSV/formula injection):
     * a patient named "=HYPERLINK(...)" must not become a live formula in Excel.
     */
    public static function csvSafe(mixed $value): mixed
    {
        if (is_string($value) && $value !== '' && ! is_numeric($value) && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }

    /**
     * CSV with a UTF-8 BOM so Excel opens it with the right characters.
     */
    protected function csv(string $title, Carbon $from, Carbon $to, array $result): StreamedResponse
    {
        $filename = str($title)->slug().'_'.$from->format('Ymd').'-'.$to->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($result) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_map(fn ($c) => $c[0], $result['columns']));
            foreach ($result['rows'] as $row) {
                fputcsv($out, array_map(fn ($key) => self::csvSafe($row[$key] ?? ''), array_keys($result['columns'])));
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
