<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Academic\Progression\Models\ProgramEnrollment;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Queries\Operations\ListDueItemsQuery;
use App\Modules\Finance\Queries\Operations\ListExamResitHandoffQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    actingAs(User::factory()->create());

    $this->campus = Campus::factory()->create();
    $this->semester = Semester::factory()->active()->create();
    $this->program = Program::factory()->create();

    session(['current_campus_id' => $this->campus->id]);
    app()->singleton('campus', fn () => $this->campus);
});

/**
 * A student whose legacy `students.status` column has drifted away from the
 * Progression-owned primary enrollment (the column is not written back on
 * lifecycle transitions).
 */
function driftedLifecycleStudent(object $ctx, string $code, string $legacyStatus, string $enrollmentStatus, ?string $studyStage): Student
{
    $student = Student::factory()
        ->forCampus($ctx->campus)
        ->forProgram($ctx->program)
        ->state([
            'student_id' => $code,
            'full_name' => "Student {$code}",
            'email' => strtolower($code).'@example.com',
            'status' => $legacyStatus,
            'intake' => 1,
            'intake_mode' => 'sequential',
            'intake_semester_id' => $ctx->semester->id,
        ])
        ->create();

    ProgramEnrollment::create([
        'student_id' => $student->id,
        'program_id' => $student->program_id,
        'curriculum_version_id' => $student->curriculum_version_id,
        'intake_semester_id' => $student->intake_semester_id,
        'is_primary' => true,
        'enrollment_status' => $enrollmentStatus,
        'study_stage' => $studyStage,
        'source_type' => 'test_program_enrollment',
        'source_id' => $student->id,
        'source_snapshot' => [],
        'materialized_at' => now(),
    ]);

    return $student;
}

it('shows the enrollment lifecycle status on a due row, not the stale legacy column', function () {
    $student = driftedLifecycleStudent($this, 'LIFE001', 'deferred', 'active', 'intake_course');

    $dng = DngPaymentRequest::create([
        'student_id' => $student->id,
        'campus_code' => 'TEST',
        'student_code' => $student->student_id,
        'fee_type' => 'HP',
        'description' => 'Học phí',
        'semester_id' => $this->semester->id,
        'due_date' => now()->subDays(2),
        'item_id' => 'HP-ITEM-'.$student->id,
        'amount' => 1_000_000,
        'status' => DngPaymentRequest::STATUS_PUSHED_TO_DNG,
    ]);

    $row = collect(app(ListDueItemsQuery::class)->handle($this->semester->id, null, null)->items())
        ->firstWhere('id', $dng->id);

    expect($row)->not->toBeNull()
        ->and($row['student_status_label'])->toBe('Intake Course')
        ->and($row['student_status_color'])->toBe('indigo');
});

it('shows the enrollment lifecycle status on an exam-resit handoff row', function () {
    $student = driftedLifecycleStudent($this, 'LIFE002', 'intake_pre_uni_gc', 'active', 'intake_course');
    $attempt = makeApprovedExamResitAttempt($student, $this->campus, $this->semester);
    scheduleExamResitAttemptInSlot($attempt->fresh(), now()->subDays(30));

    $row = collect(app(ListExamResitHandoffQuery::class)->handle($this->semester->id, null)->items())
        ->firstWhere('source_id', $attempt->id);

    expect($row)->not->toBeNull()
        ->and($row['student_status_label'])->toBe('Intake Course')
        ->and($row['student_status_color'])->toBe('indigo');
});

it('falls back to the legacy column when the student has no primary enrollment', function () {
    $student = Student::factory()
        ->forCampus($this->campus)
        ->forProgram($this->program)
        ->state([
            'student_id' => 'LIFE003',
            'full_name' => 'Student LIFE003',
            'email' => 'life003@example.com',
            'status' => 'intake_course',
            'intake' => 1,
            'intake_mode' => 'sequential',
            'intake_semester_id' => $this->semester->id,
        ])
        ->create();

    $dng = DngPaymentRequest::create([
        'student_id' => $student->id,
        'campus_code' => 'TEST',
        'student_code' => $student->student_id,
        'fee_type' => 'HP',
        'description' => 'Học phí',
        'semester_id' => $this->semester->id,
        'due_date' => now()->subDays(2),
        'item_id' => 'HP-ITEM-legacy-'.$student->id,
        'amount' => 1_000_000,
        'status' => DngPaymentRequest::STATUS_PUSHED_TO_DNG,
    ]);

    $row = collect(app(ListDueItemsQuery::class)->handle($this->semester->id, null, null)->items())
        ->firstWhere('id', $dng->id);

    expect($row)->not->toBeNull()
        ->and($row['student_status_label'])->toBe('Intake Course');
});
