<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Integrations\Claims\ElectronicClaimService;
use App\Integrations\Identity\NinVerifier;
use App\Integrations\Pacs\PacsService;
use App\Integrations\Payments\OnlinePaymentService;
use App\Models\IntegrationMessage;
use App\Models\LabAnalyzer;
use App\Models\LabTest;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

/**
 * Administration → Integrations: status, connection tests, message log,
 * and lab analyser set-up (tokens and code mappings).
 */
class IntegrationController extends Controller
{
    public function index(Request $request, PacsService $pacs, OnlinePaymentService $payments, ElectronicClaimService $claims, NinVerifier $nin): View
    {
        $channel = array_key_exists((string) $request->query('channel'), IntegrationMessage::CHANNELS) ? $request->query('channel') : null;

        return view('admin.integrations.index', [
            'status' => [
                'lab' => LabAnalyzer::where('is_active', true)->exists(),
                'pacs' => $pacs->enabled(),
                'payment' => $payments->enabled(),
                'claims' => $claims->configured(),
                'nin' => $nin->enabled(),
            ],
            'analyzers' => LabAnalyzer::withCount('mappings')->orderBy('name')->get(),
            'messages' => IntegrationMessage::with('user')->when($channel, fn ($q, $c) => $q->where('channel', $c))->latest('id')->paginate(30)->withQueryString(),
            'channel' => $channel,
            'errorsToday' => IntegrationMessage::where('status', 'error')->where('created_at', '>=', today())->count(),
        ]);
    }

    public function testPacs(PacsService $pacs): RedirectResponse
    {
        try {
            $message = $pacs->test();
            IntegrationMessage::record('pacs', 'out', 'ok', 'Connection test passed');

            return back()->with('success', $message);
        } catch (Throwable $e) {
            IntegrationMessage::record('pacs', 'out', 'error', 'Connection test failed: '.$e->getMessage());

            return back()->with('error', 'PACS test failed: '.$e->getMessage());
        }
    }

    public function message(IntegrationMessage $message): View
    {
        return view('admin.integrations.message', ['message' => $message->load('user')]);
    }

    // ------------------------------------------------------------------ lab analysers

    public function storeAnalyzer(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'alpha_dash', 'max:20', Rule::unique('lab_analyzers', 'code')],
        ]);

        [$analyzer, $token] = LabAnalyzer::register($data['name'], $data['code']);
        Audit::log('lab_analyzer_created', "Lab analyser {$analyzer->name} ({$analyzer->code}) added", $analyzer);

        return redirect()->route('admin.integrations.analyzer', $analyzer)->with('analyzer_token', $token)
            ->with('success', 'Analyser added. Copy its token now — it is shown only once.');
    }

    public function analyzer(LabAnalyzer $analyzer): View
    {
        return view('admin.integrations.analyzer', [
            'analyzer' => $analyzer->load('mappings.test', 'mappings.parameter'),
            'tests' => LabTest::with('parameters')->where('is_active', true)->orderBy('name')->get(),
            'recent' => IntegrationMessage::where('channel', 'lab')->where('summary', 'like', $analyzer->code.':%')->latest('id')->limit(15)->get(),
        ]);
    }

    public function saveMappings(Request $request, LabAnalyzer $analyzer): RedirectResponse
    {
        $request->merge(['map' => collect($request->input('map', []))->filter(fn ($m) => filled($m['analyzer_code'] ?? null))->values()->all()]);
        $data = $request->validate([
            'map' => ['array', 'max:500'],
            'map.*.analyzer_code' => ['required', 'string', 'max:50', 'distinct:ignore_case'],
            'map.*.target' => ['required', 'regex:/^\d+(:\d+)?$/'],
        ], ['map.*.target.required' => 'Choose the test/result field for every analyser code.', 'map.*.analyzer_code.distinct' => 'Each analyser code can only be mapped once.']);

        $rows = collect($data['map'] ?? [])->map(function ($m) {
            [$testId, $paramId] = array_pad(explode(':', $m['target']), 2, null);

            return ['analyzer_code' => trim($m['analyzer_code']), 'lab_test_id' => (int) $testId, 'lab_test_parameter_id' => $paramId ? (int) $paramId : null];
        });

        $analyzer->mappings()->delete();
        $analyzer->mappings()->createMany($rows->all());
        Audit::log('lab_analyzer_mapped', "Code mappings for {$analyzer->code} saved ({$rows->count()})", $analyzer);

        return back()->with('success', $rows->count().' code mapping(s) saved.');
    }

    public function regenerateToken(LabAnalyzer $analyzer): RedirectResponse
    {
        $token = $analyzer->newToken();
        $analyzer->save();
        Audit::log('lab_analyzer_token', "New API token issued for {$analyzer->code}; the old one stops working", $analyzer);

        return back()->with('analyzer_token', $token)->with('success', 'New token issued. Update it in the analyser/middleware — the old token no longer works.');
    }

    public function toggleAnalyzer(LabAnalyzer $analyzer): RedirectResponse
    {
        $analyzer->forceFill(['is_active' => ! $analyzer->is_active])->save();

        return back()->with('success', $analyzer->name.' '.($analyzer->is_active ? 'switched on.' : 'switched off — its messages will be refused.'));
    }

    public static function endpoint(): string
    {
        return Str::finish(url('api/v1/lab/results'), '');
    }
}
