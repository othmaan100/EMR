<?php

namespace App\Services;

use App\Models\Requisition;
use App\Models\StoreItem;
use App\Models\StoreMovement;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * General stores: every stock change goes through move(), which keeps the
 * balance, the weighted average cost and the ledger in step.
 */
class StoresService
{
    /**
     * Add stock (delivery, donation, return). Updates the average cost.
     */
    public function receive(StoreItem $item, int $quantity, ?float $unitCost, User $by, ?Model $reference = null, ?string $reason = null): StoreMovement
    {
        return DB::transaction(function () use ($item, $quantity, $unitCost, $by, $reference, $reason) {
            $item = StoreItem::lockForUpdate()->findOrFail($item->id);

            if ($unitCost !== null) {
                $onHand = max(0, $item->quantity_on_hand);
                $item->average_cost = round(($onHand * $item->average_cost + $quantity * $unitCost) / max(1, $onHand + $quantity), 2);
            }

            return $this->move($item, 'receipt', $quantity, $unitCost ?? $item->average_cost, $by, $reference, $reason);
        });
    }

    /**
     * Stock-count correction or write-off (negative only for write-offs).
     */
    public function adjust(StoreItem $item, int $change, string $type, string $reason, User $by): StoreMovement
    {
        $movement = DB::transaction(function () use ($item, $change, $type, $reason, $by) {
            $item = StoreItem::lockForUpdate()->findOrFail($item->id);
            if ($item->quantity_on_hand + $change < 0) {
                throw ValidationException::withMessages(['quantity' => "Only {$item->quantity_on_hand} {$item->unit} in stock."]);
            }

            return $this->move($item, $type, $change, $item->average_cost, $by, null, $reason);
        });

        Audit::log('store_stock_adjusted', "Store stock {$type} of {$change} on {$item->name}: {$reason}", $item);

        return $movement;
    }

    // ------------------------------------------------------------------ requisitions

    /**
     * @param  list<array{store_item_id: int, quantity: int}>  $lines
     */
    public function requisition(int $departmentId, array $lines, ?string $neededBy, ?string $notes, User $by): Requisition
    {
        $lines = collect($lines)->groupBy('store_item_id')
            ->map(fn ($g, $id) => ['store_item_id' => (int) $id, 'quantity_requested' => (int) $g->sum('quantity')]);

        return DB::transaction(function () use ($departmentId, $lines, $neededBy, $notes, $by) {
            $req = new Requisition(['department_id' => $departmentId, 'needed_by' => $neededBy, 'notes' => $notes]);
            $req->requisition_number = ConsultationService::number('REQ');
            $req->status = 'submitted';
            $req->requested_by = $by->id;
            $req->save();
            $req->items()->createMany($lines->values()->all());

            return $req;
        });
    }

    /**
     * Issue stock against a requisition (all or part of each line).
     *
     * @param  array<int, int>  $quantities  requisition_item_id => quantity to issue now
     */
    public function issue(Requisition $req, array $quantities, User $by): int
    {
        if (! $req->isOpen()) {
            throw ValidationException::withMessages(['status' => "{$req->requisition_number} is {$req->statusLabel()}."]);
        }

        return DB::transaction(function () use ($req, $quantities, $by) {
            $issued = 0;
            $errors = [];

            foreach ($req->items()->with('item')->get() as $line) {
                $qty = (int) ($quantities[$line->id] ?? 0);
                if ($qty <= 0) {
                    continue;
                }
                if ($qty > $line->outstanding()) {
                    $errors["issue.{$line->id}"] = "{$line->item->name}: only {$line->outstanding()} still requested.";
                    continue;
                }

                $item = StoreItem::lockForUpdate()->findOrFail($line->store_item_id);
                if ($qty > $item->quantity_on_hand) {
                    $errors["issue.{$line->id}"] = "{$item->name}: only {$item->quantity_on_hand} {$item->unit} in stock.";
                    continue;
                }

                $this->move($item, 'issue', -$qty, $item->average_cost, $by, $req, null, $req->department_id);
                $line->increment('quantity_issued', $qty);
                $issued++;
            }

            if ($errors) {
                throw ValidationException::withMessages($errors);
            }
            if ($issued === 0) {
                throw ValidationException::withMessages(['issue' => 'Enter a quantity to issue for at least one item.']);
            }

            $complete = $req->items()->get()->every(fn ($l) => $l->outstanding() === 0);
            $req->forceFill(['status' => $complete ? 'issued' : 'partially_issued', 'issued_by' => $by->id, 'issued_at' => now()])->save();

            return $issued;
        });
    }

    /**
     * Store rejects the rest of a requisition, or the requester cancels it.
     */
    public function close(Requisition $req, string $status, string $reason, User $by): void
    {
        if (! $req->isOpen()) {
            throw ValidationException::withMessages(['status' => "{$req->requisition_number} is already {$req->statusLabel()}."]);
        }
        // Partly issued requisitions are closed as "issued" (what was given stands).
        $final = $req->status === 'partially_issued' ? 'issued' : $status;
        $req->forceFill(['status' => $final, 'closed_reason' => $reason])->save();
        Audit::log('requisition_closed', "{$req->requisition_number} closed ({$status}): {$reason}", $req);
    }

    protected function move(StoreItem $item, string $type, int $quantity, ?float $unitCost, User $by, ?Model $reference, ?string $reason, ?int $departmentId = null): StoreMovement
    {
        $item->quantity_on_hand += $quantity;
        $item->save();

        $movement = new StoreMovement([
            'store_item_id' => $item->id,
            'type' => $type,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'balance_after' => $item->quantity_on_hand,
            'department_id' => $departmentId,
            'reason' => $reason,
            'user_id' => $by->id,
        ]);
        $movement->reference()->associate($reference);
        $movement->save();

        return $movement;
    }
}
