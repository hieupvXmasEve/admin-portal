<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support;

use App\Models\DeferCase;

/**
 * Course-level (COURSES-scope) defer charge-skip policy.
 *
 * FULL-scope semester skipping moved to a registration_status = 'defer' signal
 * in FIN-REV-020-02 (M2) — see DeferChargeResolver::isSemesterEnrollmentDeferred.
 * This helper now governs only the per-course (COURSES-scope) generation path.
 */
final class DeferChargePolicy
{
    public static function shouldSkipCourse(array $deferCase, array $deferItem, int $semesterId): bool
    {
        if (($deferCase['scope_type'] ?? null) !== DeferCase::SCOPE_COURSES) {
            return false;
        }

        if (! self::isPreservePolicy($deferCase, $deferItem)) {
            return false;
        }

        if (! self::isWithinApplicableSemester($deferCase, $semesterId)) {
            return false;
        }

        return self::isEligibleByApplyOnce(
            (bool) ($deferCase['applies_once'] ?? true),
            $deferItem['applied_at'] ?? null,
            $deferItem['applied_semester_id'] ?? null
        );
    }

    private static function isPreservePolicy(array $deferCase, array $deferItem): bool
    {
        $policy = $deferItem['fee_policy'] ?? $deferCase['fee_policy'] ?? null;

        return $policy === DeferCase::POLICY_PRESERVE;
    }

    private static function isWithinApplicableSemester(array $deferCase, int $semesterId): bool
    {
        $appliesUntil = $deferCase['applies_until_semester_id'] ?? null;

        if ($appliesUntil === null) {
            return true;
        }

        return (int) $appliesUntil >= $semesterId;
    }

    private static function isEligibleByApplyOnce(bool $appliesOnce, mixed $appliedAt, mixed $appliedSemesterId): bool
    {
        if (! $appliesOnce) {
            return true;
        }

        if (! empty($appliedAt)) {
            return false;
        }

        return empty($appliedSemesterId);
    }
}
