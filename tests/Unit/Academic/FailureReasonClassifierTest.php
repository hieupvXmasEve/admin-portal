<?php

declare(strict_types=1);

use App\Models\AcademicRecord;
use App\Modules\Academic\Support\FailureReasonClassifier as Classifier;

/**
 * @param  array<string, mixed>  $overrides
 * @return array{is_passed: bool, failure_reason: ?string, snapshot: array<string, mixed>}
 */
function classify(array $overrides = []): array
{
    $args = array_merge([
        'finalPercentage' => 80.0,
        'gradeThreshold' => 60.0,
        'present' => 10,
        'late' => 0,
        'absent' => 0,
        'notRecorded' => 0,
        'totalSessions' => 10,
        'attendanceThreshold' => 80.0,
        'overridePass' => false,
        'overrideIsPassed' => false,
    ], $overrides);

    return Classifier::classify(
        $args['finalPercentage'],
        $args['gradeThreshold'],
        $args['present'],
        $args['late'],
        $args['absent'],
        $args['notRecorded'],
        $args['totalSessions'],
        $args['attendanceThreshold'],
        $args['overridePass'],
        $args['overrideIsPassed'],
    );
}

it('passes a student who clears both grade and attendance', function () {
    $r = classify(['finalPercentage' => 80, 'present' => 9, 'absent' => 1]); // 90% attendance
    expect($r['is_passed'])->toBeTrue()
        ->and($r['failure_reason'])->toBeNull()
        ->and($r['snapshot']['attendance_evidence_state'])->toBe('clean');
});

it('labels grade-only failure as grade_failed (resit lane)', function () {
    $r = classify(['finalPercentage' => 45, 'present' => 10]); // 100% attendance, grade fails
    expect($r['is_passed'])->toBeFalse()
        ->and($r['failure_reason'])->toBe(AcademicRecord::FAILURE_GRADE_FAILED)
        ->and($r['snapshot']['attendance_failed'])->toBeFalse();
});

it('labels attendance-only failure as attendance_failed (retake lane)', function () {
    $r = classify(['finalPercentage' => 75, 'present' => 5, 'absent' => 5]); // 50% attendance, grade ok
    expect($r['is_passed'])->toBeFalse()
        ->and($r['failure_reason'])->toBe(AcademicRecord::FAILURE_ATTENDANCE_FAILED)
        ->and($r['snapshot']['attendance_pct'])->toBe(50.0);
});

it('labels combined failure as both_failed (retake lane)', function () {
    $r = classify(['finalPercentage' => 40, 'present' => 4, 'absent' => 6]); // 40% attendance + grade fail
    expect($r['is_passed'])->toBeFalse()
        ->and($r['failure_reason'])->toBe(AcademicRecord::FAILURE_BOTH_FAILED);
});

it('excludes excused and not-recorded sessions from the attendance denominator', function () {
    // present 8, absent 2, plus 5 excused (totalSessions 15) → denominator = 10 → 80% → passes.
    $r = classify(['finalPercentage' => 70, 'present' => 8, 'late' => 0, 'absent' => 2, 'totalSessions' => 15, 'notRecorded' => 0]);
    expect($r['snapshot']['attendance_pct'])->toBe(80.0)
        ->and($r['failure_reason'])->toBeNull();
});

it('treats a course with no recorded attendance as grade-only (attendance not applicable)', function () {
    $r = classify(['finalPercentage' => 85, 'present' => 0, 'late' => 0, 'absent' => 0, 'totalSessions' => 0]);
    expect($r['is_passed'])->toBeTrue()
        ->and($r['snapshot']['attendance_pct'])->toBeNull()
        ->and($r['snapshot']['attendance_evidence_state'])->toBe('no_sessions');

    // grade fail with no attendance data → grade_failed only
    $f = classify(['finalPercentage' => 30, 'present' => 0, 'absent' => 0, 'totalSessions' => 0]);
    expect($f['failure_reason'])->toBe(AcademicRecord::FAILURE_GRADE_FAILED);
});

it('soft-flags not_recorded evidence without failing the student on it', function () {
    // 5 present, 0 absent, 5 not-recorded → recorded denom = 5 → 100% (does not fail),
    // but evidence is incomplete so the snapshot flags not_recorded for the resit gate.
    $r = classify(['finalPercentage' => 75, 'present' => 5, 'absent' => 0, 'notRecorded' => 5, 'totalSessions' => 10]);
    expect($r['is_passed'])->toBeTrue()
        ->and($r['snapshot']['attendance_evidence_state'])->toBe('not_recorded')
        ->and($r['snapshot']['attendance_failed'])->toBeFalse();
});

it('respects override_pass and labels overridden failures manual_failed', function () {
    $passed = classify(['finalPercentage' => 20, 'present' => 0, 'absent' => 10, 'overridePass' => true, 'overrideIsPassed' => true]);
    expect($passed['is_passed'])->toBeTrue()
        ->and($passed['failure_reason'])->toBeNull();

    $failed = classify(['finalPercentage' => 90, 'present' => 10, 'overridePass' => true, 'overrideIsPassed' => false]);
    expect($failed['is_passed'])->toBeFalse()
        ->and($failed['failure_reason'])->toBe(AcademicRecord::FAILURE_MANUAL_FAILED);
});
