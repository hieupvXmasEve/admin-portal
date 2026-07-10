<?php

declare(strict_types=1);

namespace App\Modules\Finance\Enums;

/**
 * How a type is cancelled/voided once accepted.
 *
 * Code-owned behaviour wiring (ADR-0027) — not staff-configurable.
 */
enum CancellationPolicy: string
{
    /** Source module calls FinanceObligationCancellationContract (retake/resit). */
    case SourceCancellationContract = 'source_cancellation_contract';

    /** Staff voids the materialized charge / obligation via Finance UI. */
    case StaffVoid = 'staff_void';

    /** Closed/adjusted by a Finance policy workflow (e.g. defer settlement). */
    case PolicySettlement = 'policy_settlement';

    /** Credit/discount revoke/expire path — not charge void. */
    case EntitlementRevoke = 'entitlement_revoke';

    /** Explicitly not cancelled as a standalone type (audit/split later). */
    case AuditPending = 'audit_pending';

    /**
     * Formally retired type: no new generation, no active DNG reverse-map.
     * Historical enum/CHECK value may remain until wave-7 cleanup.
     */
    case Retired = 'retired';
}
