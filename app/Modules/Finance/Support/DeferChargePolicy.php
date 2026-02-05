<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support;

use App\Models\DeferCase;

final class DeferChargePolicy
{
    public static function shouldSkipFullSemester(array $deferCase, int $semesterId): bool
    {
        if (($deferCase['scope_type'] ?? null) !== DeferCase::SCOPE_FULL) {
            return false;
        }

        if (($deferCase['fee_policy'] ?? null) !== DeferCase::POLICY_PRESERVE) {
            return false;
        }

        if (! self::isWithinApplicableSemester($deferCase, $semesterId)) {
            return false;
        }

        return self::isEligibleByApplyOnce(
            (bool) ($deferCase['applies_once'] ?? true),
            $deferCase['applied_at'] ?? null,
            $deferCase['applied_semester_id'] ?? null
        );
    }

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
