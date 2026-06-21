<?php

declare(strict_types=1);

use App\Enums\StudentActionType;
use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\DeferCase;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Academic\Actions\RecordStudentActionAction;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * FIN-REV-020 — Phase 4: runtime FULL-scope defer itemization.
 *
 * A full-scope academic defer must capture item-level course evidence
 * (defer_case_items) for the student's active enrollments in the defer
 * semester and mark those registrations non-billable (registration_status
 * = 'defer'), mirroring what COURSES-scope defer already does.
 */
function fullScopeDeferFixture(): array
{
    $campus = Campus::factory()->create();
    $program = Program::factory()->create();
    $fromSemester = Semester::factory()->active()->create([
        'code' => '2026SP',
        'name' => 'Spring 2026',
        'start_date' => '2026-01-01',
        'end_date' => '2026-05-31',
    ]);
    $returnSemester = Semester::factory()->create([
        'code' => '2026FA',
        'name' => 'Fall 2026',
        'start_date' => '2026-08-01',
        'end_date' => '2026-12-31',
        'is_active' => false,
    ]);
    $user = User::factory()->create();

    $student = Student::factory()
        ->forCampus($campus)
        ->forProgram($program)
        ->create([
            'student_id' => 'FULL-DEFER-01',
            'status' => 'intake_course',
            'intake' => 1,
            'intake_semester_id' => $fromSemester->id,
        ]);

    return compact('campus', 'program', 'fromSemester', 'returnSemester', 'user', 'student');
}

function makeRegistration(int $studentId, int $semesterId, string $status): CourseRegistration
{
    $offering = CourseOffering::factory()->create(['semester_id' => $semesterId]);

    return CourseRegistration::create([
        'student_id' => $studentId,
        'course_offering_id' => $offering->id,
        'semester_id' => $semesterId,
        'registration_status' => $status,
        'registration_date' => now(),
        'registration_method' => 'admin_override',
        'credit_hours' => 3,
        'credit_points' => 3,
        'attempt_number' => 1,
    ]);
}

it('itemizes only active registrations and marks them defer for a full-scope defer', function () {
    ['fromSemester' => $from, 'returnSemester' => $return, 'user' => $user, 'student' => $student] = fullScopeDeferFixture();

    $activeOne = makeRegistration($student->id, $from->id, 'confirmed');
    $activeTwo = makeRegistration($student->id, $from->id, 'registered');
    $completed = makeRegistration($student->id, $from->id, 'completed');

    $log = RecordStudentActionAction::run([
        'student_id' => $student->id,
        'action_type' => StudentActionType::ACADEMIC_DEFER->value,
        'reason' => 'Full semester defer',
        'changed_by_user_id' => $user->id,
        'from_semester_id' => $from->id,
        'return_semester_id' => $return->id,
        'defer_scope_type' => 'FULL',
        'defer_fee_policy' => 'FORFEIT',
    ]);

    $deferCase = DeferCase::where('student_action_log_id', $log->id)->firstOrFail();

    $itemizedRegistrationIds = $deferCase->items()->pluck('course_registration_id')->sort()->values()->all();

    expect($deferCase->scope_type)->toBe(DeferCase::SCOPE_FULL)
        ->and($itemizedRegistrationIds)->toBe(collect([$activeOne->id, $activeTwo->id])->sort()->values()->all())
        ->and($activeOne->fresh()->registration_status)->toBe('defer')
        ->and($activeTwo->fresh()->registration_status)->toBe('defer')
        ->and($completed->fresh()->registration_status)->toBe('completed');
});
