<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Integrations\Lab\Hl7Message;
use App\Integrations\Lab\ResultIngestor;
use App\Models\IntegrationMessage;
use App\Models\LabAnalyzer;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * POST /api/v1/lab/results — Authorization: Bearer <analyser token>
 *
 * Body: an HL7 v2 ORU^R01 message (Content-Type text/plain or application/hl7-v2),
 * or JSON {"sample_id": "LAB2026-000123", "results": [{"code": "WBC", "value": "6.1"}]}.
 */
class LabResultController extends Controller
{
    public function __invoke(Request $request, ResultIngestor $ingestor): Response
    {
        $analyzer = LabAnalyzer::findByToken($request->bearerToken());
        $isJson = $request->isJson();

        if (! $analyzer) {
            IntegrationMessage::record('lab', 'in', 'error', 'Rejected: missing or invalid analyser token', null, null);

            return $isJson ? response()->json(['status' => 'error', 'message' => 'Invalid token.'], 401)
                : response(Hl7Message::ack(null, false, 'Invalid token'), 401, ['Content-Type' => 'application/hl7-v2']);
        }

        if ($isJson) {
            $validator = Validator::make($request->all(), [
                'sample_id' => ['required', 'string', 'max:50'],
                'results' => ['required', 'array', 'min:1', 'max:200'],
                'results.*.code' => ['required', 'string', 'max:50'],
                'results.*.value' => ['required', 'max:500'],
            ]);
            if ($validator->fails()) {
                return response()->json(['status' => 'error', 'message' => implode(' ', $validator->errors()->all())], 422);
            }

            $outcome = $ingestor->ingest($analyzer, $request->input('sample_id'), array_map(fn ($r) => ['code' => (string) $r['code'], 'value' => (string) $r['value']],
                $request->input('results')), json_encode($request->all()));

            return response()->json($outcome, $outcome['status'] === 'error' ? 422 : 200);
        }

        $raw = (string) $request->getContent();
        try {
            $message = Hl7Message::parse($raw);
        } catch (Throwable $e) {
            IntegrationMessage::record('lab', 'in', 'error', "{$analyzer->code}: {$e->getMessage()}", null, $raw);

            return response(Hl7Message::ack(null, false, $e->getMessage()), 400, ['Content-Type' => 'application/hl7-v2']);
        }

        if (! $message['samples']) {
            IntegrationMessage::record('lab', 'in', 'error', "{$analyzer->code}: message had no results (OBX) with a sample id (OBR)", null, $raw);

            return response(Hl7Message::ack($message['control_id'], false, 'No results found'), 422, ['Content-Type' => 'application/hl7-v2']);
        }

        $ok = true;
        $texts = [];
        foreach ($message['samples'] as $sampleId => $results) {
            $outcome = $ingestor->ingest($analyzer, (string) $sampleId, $results, $raw);
            $ok = $ok && $outcome['status'] !== 'error';
            $texts[] = $outcome['message'];
        }

        return response(Hl7Message::ack($message['control_id'], $ok, implode('; ', $texts), $message['sending_app']), $ok ? 200 : 422,
            ['Content-Type' => 'application/hl7-v2']);
    }
}
