<?php

declare(strict_types=1);

use App\Models\DeferCase;
use App\Modules\Finance\Support\DeferChargePolicy;

// FULL-scope semester skipping is no longer policy-driven: FIN-REV-020-02 (M2)
// moved it to a registration_status = 'defer' signal
// (DeferChargeResolver::isSemesterEnrollmentDeferred). Only the COURSES-scope
// per-item policy remains here.

it('skips course charge when preserve applies at item level', function () {
    $case = [
        'scope_type' => DeferCase::SCOPE_COURSES,
        'fee_policy' => DeferCase::POLICY_PRESERVE,
        'applies_once' => true,
        'applies_until_semester_id' => null,
        'applied_at' => null,
        'applied_semester_id' => null,
    ];

    $item = [
        'fee_policy' => DeferCase::POLICY_PRESERVE,
        'applied_at' => null,
        'applied_semester_id' => null,
    ];

    expect(DeferChargePolicy::shouldSkipCourse($case, $item, 8))->toBeTrue();
});

it('does not skip course charge when policy is forfeit', function () {
    $case = [
        'scope_type' => DeferCase::SCOPE_COURSES,
        'fee_policy' => DeferCase::POLICY_FORFEIT,
        'applies_once' => true,
        'applies_until_semester_id' => null,
        'applied_at' => null,
        'applied_semester_id' => null,
    ];

    $item = [
        'fee_policy' => DeferCase::POLICY_FORFEIT,
        'applied_at' => null,
        'applied_semester_id' => null,
    ];

    expect(DeferChargePolicy::shouldSkipCourse($case, $item, 8))->toBeFalse();
});
