<?php

declare(strict_types=1);

use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Support\LifecycleDueItemPredicate;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('applies active collection scope using a join for campus scoping, not nested whereHas', function () {
    $query = DngPaymentRequest::query();

    LifecycleDueItemPredicate::applyActiveCollectionScope($query, 123);

    $sql = strtolower($query->toSql());
    $normalizedSql = str_replace(['`', '"'], '', $sql);

    // The campus scope still goes through a join (not whereHas('student', ...)).
    // A `whereExists` against program_enrollments is expected now: status is
    // resolved from the live study_stage, with the legacy students.status
    // column only as a fallback for students never materialized.
    expect($sql)->toContain('join')
        ->and($sql)->toContain('exists')
        ->and($sql)->toContain('program_enrollments')
        ->and($normalizedSql)->toContain('join students')
        ->and($normalizedSql)->toContain('students.campus_id');
});
