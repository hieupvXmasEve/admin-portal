<?php

declare(strict_types=1);

use App\Models\DeferCase;
use App\Modules\Finance\Support\DeferChargePolicy;

it('skips full semester charges when preserve is applicable', function () {
    $case = [
        'scope_type' => DeferCase::SCOPE_FULL,
        'fee_policy' => DeferCase::POLICY_PRESERVE,
        'applies_once' => true,
        'applies_until_semester_id' => 10,
        'applied_at' => null,
        'applied_semester_id' => null,
    ];

    expect(DeferChargePolicy::shouldSkipFullSemester($case, 9))->toBeTrue();
});

it('does not skip full semester when already applied', function () {
    $case = [
        'scope_type' => DeferCase::SCOPE_FULL,
        'fee_policy' => DeferCase::POLICY_PRESERVE,
        'applies_once' => true,
        'applies_until_semester_id' => 10,
        'applied_at' => '2026-02-04 10:00:00',
        'applied_semester_id' => 9,
    ];

    expect(DeferChargePolicy::shouldSkipFullSemester($case, 9))->toBeFalse();
});

it('does not skip full semester when expired', function () {
    $case = [
        'scope_type' => DeferCase::SCOPE_FULL,
        'fee_policy' => DeferCase::POLICY_PRESERVE,
        'applies_once' => true,
        'applies_until_semester_id' => 3,
        'applied_at' => null,
        'applied_semester_id' => null,
    ];

    expect(DeferChargePolicy::shouldSkipFullSemester($case, 4))->toBeFalse();
});

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
