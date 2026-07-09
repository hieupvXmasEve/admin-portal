<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support;

use Illuminate\Support\Str;

/**
 * Neutral source triple helpers for Finance-owned intakes (ADR-0026 §2.1).
 *
 * Finance mints source_ref for generators it owns (Batch Studio, CSV non-academic,
 * manual fee page). External modules never mint finance source refs.
 */
final class FinanceOwnedObligationSource
{
    public const SOURCE_SYSTEM = 'finance';

    public const NON_ACADEMIC_BATCH = 'non_academic_batch';

    public const CSV_IMPORT = 'csv_import';

    public const MANUAL_FEE = 'manual_fee';

    /**
     * Mint a unique source_ref for one non-academic batch row (e.g. BHYT).
     *
     * Includes student/semester for audit readability; ULID keeps the source
     * quad unique so void-then-reissue does not collide with a prior obligation.
     */
    public static function nonAcademicBatchRef(
        string $obligationType,
        int $studentId,
        int $semesterId,
    ): string {
        return sprintf(
            'non_academic_batch:%s:student:%d:semester:%d:%s',
            $obligationType,
            $studentId,
            $semesterId,
            Str::ulid()->toBase32(),
        );
    }

    /**
     * Synthetic source_ref for legacy BHYT charges that pre-date intake.
     * Stable per charge so backfill is idempotent under the source quad unique key.
     */
    public static function legacyBhytChargeRef(int $chargeId): string
    {
        return "legacy:bhyt:charge:{$chargeId}";
    }

    /**
     * Synthetic source_ref for legacy tuition_term charges that pre-date intake.
     * Stable per charge so backfill is idempotent under the source quad unique key
     * and never collides with post-cutover mint refs
     * (`tuition_term:student:{id}:semester:{id}`).
     */
    public static function legacyTuitionTermChargeRef(int $chargeId): string
    {
        return "legacy:tuition_term:charge:{$chargeId}";
    }

    /**
     * Synthetic source_ref for legacy defer_credit charges that pre-date entitlement intake.
     * Stable per charge so conversion is idempotent under the source quad unique key.
     */
    public static function legacyDeferCreditChargeRef(int $chargeId): string
    {
        return "legacy:defer_credit:charge:{$chargeId}";
    }

    /**
     * Synthetic source_ref for legacy voucher_credit charges that pre-date discount entitlement intake.
     * Stable per charge so conversion is idempotent under the source quad unique key.
     */
    public static function legacyVoucherCreditChargeRef(int $chargeId): string
    {
        return "legacy:voucher_credit:charge:{$chargeId}";
    }
}
