<?php

namespace App\Integrations\Lab;

use App\Models\IntegrationMessage;
use App\Models\LabAnalyzer;
use App\Models\LabOrder;
use App\Services\LabService;
use Illuminate\Validation\ValidationException;

/**
 * Files analyser results onto lab orders through the normal LabService, so
 * reference ranges, flags and the two-person verification rule all apply.
 * Results arrive as "entered"; a scientist must still verify them.
 */
class ResultIngestor
{
    public function __construct(protected LabService $lab) {}

    /**
     * @param  list<array{code: string, value: string, unit?: ?string}>  $results
     * @return array{status: string, message: string, saved: int, skipped: list<string>}
     */
    public function ingest(LabAnalyzer $analyzer, string $sampleId, array $results, ?string $raw = null): array
    {
        $analyzer->forceFill(['last_message_at' => now()])->save();
        $order = LabOrder::with('items.test.parameters', 'items.results')->where('order_number', trim($sampleId))->first();

        $fail = function (string $message) use ($analyzer, $sampleId, $raw, $order) {
            IntegrationMessage::record('lab', 'in', 'error', "{$analyzer->code}: {$message}", $sampleId, $raw, $order);

            return ['status' => 'error', 'message' => $message, 'saved' => 0, 'skipped' => []];
        };

        if (! $order) {
            return $fail("Unknown sample \"{$sampleId}\" (the sample barcode must be the lab order number).");
        }
        if (! in_array($order->status, ['collected', 'in_progress'], true)) {
            return $fail("Order {$order->order_number} is \"{$order->statusLabel()}\"; results can only be added after collection and before verification.");
        }

        $mappings = $analyzer->mappings()->get()->keyBy(fn ($m) => mb_strtolower($m->analyzer_code));
        $values = [];
        $skipped = [];

        foreach ($results as $result) {
            $mapping = $mappings->get(mb_strtolower($result['code']));
            $item = $mapping ? $order->items->first(fn ($i) => $i->lab_test_id === $mapping->lab_test_id && in_array($i->status, ['collected', 'resulted'], true)) : null;

            if (! $mapping) {
                $skipped[] = "{$result['code']} (not mapped)";

                continue;
            }
            if (! $item) {
                $skipped[] = "{$result['code']} (test not on this order, or already verified)";

                continue;
            }

            // Start from what is already there so partial messages don't erase other values.
            $values[$item->id] ??= $item->results->whereNotNull('lab_test_parameter_id')->pluck('value', 'lab_test_parameter_id')->all();
            if ($mapping->lab_test_parameter_id) {
                $values[$item->id][$mapping->lab_test_parameter_id] = $result['value'];
            } else {
                $values[$item->id]['text'] = $result['value'];
            }
        }

        if (! $values) {
            return $fail('No usable results: '.implode(', ', $skipped));
        }

        try {
            $comments = $order->items->whereIn('id', array_keys($values))->pluck('comment', 'id')->all();
            $saved = $this->lab->saveResults($order, $analyzer->user, $values, $comments);
        } catch (ValidationException $e) {
            return $fail(implode(' ', $e->validator->errors()->all()));
        }

        $status = $skipped ? 'partial' : 'ok';
        $message = "{$saved} test(s) resulted on {$order->order_number}".($skipped ? '; skipped: '.implode(', ', $skipped) : '');
        IntegrationMessage::record('lab', 'in', $status, "{$analyzer->code}: {$message}", $order->order_number, $raw, $order);

        return ['status' => $status, 'message' => $message, 'saved' => $saved, 'skipped' => $skipped];
    }
}
