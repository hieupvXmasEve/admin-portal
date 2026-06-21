<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support;

/**
 * FIN-REV-020 classification vocabulary for the defer backfill dry-run.
 *
 * Itemization buckets describe whether a full-scope defer case still needs
 * item-level course evidence. Finance flags are read-only signals that inform
 * the later money-settlement phase; they never block the (non-money) item
 * backfill itself.
 */
final class DeferBackfillClassification
{
    // Itemization status (mutually exclusive).
    public const ITEMIZATION_ITEMIZABLE = 'itemizable';

    public const ITEMIZATION_ALREADY_ITEMIZED = 'already_itemized';

    public const ITEMIZATION_NO_REGISTRATION = 'no_registration';

    // Finance flags (a case may carry several).
    public const FLAG_NO_CHARGE = 'no_charge';

    public const FLAG_CHARGE_VOIDED = 'charge_voided';

    public const FLAG_HAS_ACTIVE_CHARGE = 'has_active_charge';

    public const FLAG_AMBIGUOUS_PARTIAL = 'ambiguous_partial';
}
