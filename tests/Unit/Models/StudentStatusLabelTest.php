<?php

declare(strict_types=1);

use App\Models\Student;

it('labels the pending_course_opening status instead of falling back to Unknown', function () {
    $student = new Student(['status' => 'pending_course_opening']);

    expect($student->status_label)->toBe('Pending Course Opening')
        ->and($student->status_color)->toBe('orange');
});

it('maps every declared student status to a real label and color', function () {
    // active/inactive are intentionally commented out in the accessor (they render
    // via other surfaces), so they are excluded from this completeness sweep.
    $intentionallyUnmapped = ['active', 'inactive'];

    foreach (Student::STATUSES as $status) {
        if (in_array($status, $intentionallyUnmapped, true)) {
            continue;
        }

        $student = new Student(['status' => $status]);

        expect($student->status_label)->not->toBe('Unknown', "Missing label for status [{$status}]");
        expect($student->status_color)->not->toBe('gray', "Missing color for status [{$status}]");
    }
});

it('still labels representative known statuses', function () {
    expect((new Student(['status' => 'intake_course']))->status_label)->toBe('Intake Course')
        ->and((new Student(['status' => 'deferred']))->status_label)->toBe('Deferred');
});
