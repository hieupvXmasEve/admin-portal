<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Support;

use App\Models\MerchandiseVariant;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Inventory movement primitive for merchandise variant stock.
 *
 * Global lock order (RT-15): every path in this system that needs to lock
 * more than one row across the Gold wallet and merchandise variants inside a
 * single transaction (Phase 3 checkout, refund, bulk stock ops) MUST acquire
 * locks in this order to avoid deadlocks:
 *   1. Gold wallet (see App\Services\GoldService::lockWalletForUpdate)
 *   2. Merchandise variants, ordered by merchandise_variant_id ASC
 * This service only ever locks a single variant per call, so it cannot
 * deadlock by itself — but any caller composing multiple adjustStock() calls
 * (or locking variants directly) under one transaction must sort variant ids
 * ascending first.
 */
class StockService
{
    /**
     * Adjust a variant's stock and record the movement in one transaction.
     *
     * Locks the variant row FOR UPDATE, computes quantity_before/after under
     * that lock, and — for decrements — layers a conditional
     * `WHERE stock_quantity >= ?` update as a second guard alongside the
     * unsigned column, mirroring GoldService's lock + before/after + audit
     * row pattern from the Gold ledger (Phase 1).
     *
     * @throws InvalidArgumentException when the type is not allowed, change
     *                                  is zero, or the decrement would leave
     *                                  stock negative.
     */
    public function adjustStock(
        MerchandiseVariant $variant,
        int $change,
        string $type,
        ?int $performedBy = null,
        ?string $note = null,
        ?int $redemptionOrderId = null,
    ): StockMovement {
        if ($change === 0) {
            throw new InvalidArgumentException('Stock adjustment change cannot be zero');
        }

        if (! in_array($type, StockMovement::TYPES, true)) {
            throw new InvalidArgumentException("Invalid stock movement type: {$type}");
        }

        return DB::transaction(function () use ($variant, $change, $type, $performedBy, $note, $redemptionOrderId) {
            $locked = MerchandiseVariant::whereKey($variant->id)->lockForUpdate()->firstOrFail();

            $before = (int) $locked->stock_quantity;
            $after = $before + $change;

            if ($after < 0) {
                throw new InvalidArgumentException('Insufficient stock');
            }

            if ($change < 0) {
                $affected = MerchandiseVariant::whereKey($locked->id)
                    ->where('stock_quantity', '>=', abs($change))
                    ->update(['stock_quantity' => $after]);

                if ($affected !== 1) {
                    // The lockForUpdate above should make this unreachable, but a
                    // failed conditional update is the DB-level backstop against
                    // a negative write racing the lock.
                    throw new InvalidArgumentException('Insufficient stock');
                }
            } else {
                MerchandiseVariant::whereKey($locked->id)->update(['stock_quantity' => $after]);
            }

            return StockMovement::create([
                'merchandise_variant_id' => $locked->id,
                'change' => $change,
                'quantity_before' => $before,
                'quantity_after' => $after,
                'type' => $type,
                'redemption_order_id' => $redemptionOrderId,
                'performed_by' => $performedBy,
                'note' => $note,
            ]);
        });
    }
}
