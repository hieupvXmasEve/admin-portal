<?php

declare(strict_types=1);

use App\Enums\StudentActionType;
use App\Modules\Academic\Support\StudentStatusTransitionPolicy;

it('offers only enrollment, admission deferral and dropout for a pending student', function () {
    expect(StudentStatusTransitionPolicy::selectableActions('pending'))->toBe([
        StudentActionType::STUDENT_ENROLLMENT_NE,
        StudentActionType::ADMISSION_DEFERRAL,
        StudentActionType::ACADEMIC_DROPOUT,
    ]);
});

it('offers waiting, defer, dropout and campus transfer for active study stages', function () {
    $expected = [
        StudentActionType::WAITING_COURSE_OPENING,
        StudentActionType::ACADEMIC_DEFER,
        StudentActionType::ACADEMIC_DROPOUT,
        StudentActionType::CAMPUS_TRANSFER,
    ];

    expect(StudentStatusTransitionPolicy::selectableActions('intake_pre_uni_gc'))->toBe($expected)
        ->and(StudentStatusTransitionPolicy::selectableActions('intake_course'))->toBe($expected);
});

it('never offers resume to an active study stage (the illogical case)', function () {
    expect(StudentStatusTransitionPolicy::selectableActions('intake_course'))
        ->not->toContain(StudentActionType::ACADEMIC_RESUME)
        ->and(StudentStatusTransitionPolicy::selectableActions('intake_pre_uni_gc'))
        ->not->toContain(StudentActionType::ACADEMIC_RESUME);
});

it('offers resume only from deferred and pending_course_opening', function () {
    expect(StudentStatusTransitionPolicy::selectableActions('deferred'))->toBe([
        StudentActionType::ACADEMIC_RESUME,
        StudentActionType::ACADEMIC_DEFER,
    ])->and(StudentStatusTransitionPolicy::selectableActions('pending_course_opening'))->toBe([
        StudentActionType::ACADEMIC_RESUME,
        StudentActionType::ACADEMIC_DEFER,
        StudentActionType::ACADEMIC_DROPOUT,
    ]);
});

it('offers no selectable actions for terminal statuses', function () {
    expect(StudentStatusTransitionPolicy::selectableActions('dropout'))->toBe([])
        ->and(StudentStatusTransitionPolicy::selectableActions('dropout_transfer'))->toBe([])
        ->and(StudentStatusTransitionPolicy::selectableActions('graduated'))->toBe([]);
});

it('exposes selectable action values as enum strings for the FE payload', function () {
    expect(StudentStatusTransitionPolicy::selectableActionValues('deferred'))->toBe([
        StudentActionType::ACADEMIC_RESUME->value,
        StudentActionType::ACADEMIC_DEFER->value,
    ]);
});
