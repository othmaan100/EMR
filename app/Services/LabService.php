<?php

namespace App\Services;

use App\Models\LabOrder;
use App\Models\LabOrderItem;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Laboratory workflow:
 * requested → collected → (results entered) in_progress → verified: completed.
 */
class LabService
{
    public function collect(LabOrder $order, User $by): void
    {
        $this->assertStatus($order, ['requested'], 'collect a sample for');
        app(BillingService::class)->assertPaid($order->items()->where('status', '!=', 'cancelled')->get(), 'sample collection');

        DB::transaction(function () use ($order, $by) {
            $order->forceFill([
                'status' => 'collected', 'collected_at' => now(), 'collected_by' => $by->id, 'rejection_reason' => null,
            ])->save();
            $order->items()->where('status', 'requested')->update(['status' => 'collected']);
        });
    }

    /**
     * Unsuitable sample: discard any results and send back for recollection.
     */
    public function reject(LabOrder $order, User $by, string $reason): void
    {
        $this->assertStatus($order, ['collected', 'in_progress'], 'reject the sample of');

        DB::transaction(function () use ($order, $reason) {
            foreach ($order->items()->where('status', '!=', 'cancelled')->get() as $item) {
                $item->results()->delete();
                $item->forceFill(['status' => 'requested', 'comment' => null, 'entered_by' => null, 'entered_at' => null])->save();
            }
            $order->forceFill([
                'status' => 'requested', 'collected_at' => null, 'collected_by' => null, 'rejection_reason' => $reason,
            ])->save();
        });

        Audit::log('lab_sample_rejected', "Sample for {$order->order_number} rejected: {$reason}", $order);
    }

    /**
     * @param  array<int, array<string, ?string>>  $values  item_id => [parameter_id|"text" => value]
     * @param  array<int, ?string>  $comments  item_id => comment
     */
    public function saveResults(LabOrder $order, User $by, array $values, array $comments): int
    {
        $this->assertStatus($order, ['collected', 'in_progress'], 'enter results for');

        $items = $order->items()->with('test.parameters')->whereIn('status', ['collected', 'resulted'])->get();
        $errors = [];
        $toSave = [];

        // Validate everything first so a typo never leaves a half-saved order.
        foreach ($items as $item) {
            $rows = $this->buildRows($item, $values[$item->id] ?? [], $errors);
            if ($rows !== null) {
                $toSave[] = [$item, $rows];
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        DB::transaction(function () use ($toSave, $comments, $by) {
            foreach ($toSave as [$item, $rows]) {
                $item->results()->delete();
                $item->results()->createMany($rows);
                $item->forceFill([
                    'status' => 'resulted', 'comment' => $comments[$item->id] ?? null,
                    'entered_by' => $by->id, 'entered_at' => now(),
                ])->save();
            }
        });
        $saved = count($toSave);

        $pending = $order->items()->whereIn('status', ['requested', 'collected'])->exists();
        $order->forceFill(['status' => $pending ? 'collected' : 'in_progress'])->save();

        return $saved;
    }

    public function verify(LabOrder $order, User $by): void
    {
        $this->assertStatus($order, ['in_progress'], 'verify');

        $items = $order->items()->where('status', '!=', 'cancelled')->get();

        if ($items->contains(fn (LabOrderItem $i) => $i->status !== 'resulted')) {
            throw ValidationException::withMessages(['verify' => 'All tests must have results before verification.']);
        }

        if (! setting('lab_self_verify') && $items->contains(fn (LabOrderItem $i) => $i->entered_by === $by->id)) {
            throw ValidationException::withMessages(['verify' => 'Results must be verified by a different person from the one who entered them.']);
        }

        DB::transaction(function () use ($order, $items, $by) {
            foreach ($items as $item) {
                $item->forceFill(['status' => 'verified', 'verified_by' => $by->id, 'verified_at' => now()])->save();
            }
            $order->forceFill(['status' => 'completed', 'completed_at' => now()])->save();
        });

        Audit::log('lab_results_released', "Results for {$order->order_number} verified and released", $order);

        // Deliberately generic: an SMS must never reveal which test was done.
        app(SmsNotifier::class)->resultsReady($order->patient, 'laboratory', $order->order_number);
    }

    /**
     * Turn submitted values into result rows, flagging against reference ranges.
     * Returns null when every field is blank.
     */
    protected function buildRows(LabOrderItem $item, array $input, array &$errors): ?array
    {
        $parameters = $item->test->parameters;

        if ($parameters->isEmpty()) {
            $value = trim((string) ($input['text'] ?? ''));

            return $value === '' ? null : [['name' => $item->test->name, 'value' => $value]];
        }

        $rows = [];
        foreach ($parameters as $p) {
            $value = trim((string) ($input[$p->id] ?? ''));
            if ($value === '') {
                continue;
            }

            $key = "results.{$item->id}.{$p->id}";
            if ($p->type === 'numeric' && ! is_numeric($value)) {
                $errors[$key] = "{$item->test->name} – {$p->name}: enter a number.";
                continue;
            }
            if ($p->type === 'option' && $p->options && ! in_array($value, $p->options, true)) {
                $errors[$key] = "{$item->test->name} – {$p->name}: choose a listed value.";
                continue;
            }

            $rows[] = [
                'lab_test_parameter_id' => $p->id,
                'name' => $p->name,
                'unit' => $p->unit,
                'reference' => $p->referenceLabel(),
                'value' => $value,
                'numeric_value' => $p->type === 'numeric' ? (float) $value : null,
                'flag' => $p->flagFor($value),
            ];
        }

        return $rows ?: null;
    }

    protected function assertStatus(LabOrder $order, array $allowed, string $action): void
    {
        if (! in_array($order->status, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => "Cannot {$action} {$order->order_number} while it is \"{$order->statusLabel()}\".",
            ]);
        }
    }
}
