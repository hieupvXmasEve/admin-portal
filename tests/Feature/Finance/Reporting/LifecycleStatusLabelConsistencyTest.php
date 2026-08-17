<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Academic\Progression\Models\ProgramEnrollment;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Queries\Operations\ListLifecycleDueExceptionsQuery;
use App\Modules\Finance\Queries\Reporting\ListDngLifecycleQuery;
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
 * Legacy `students.status` drifted away from the Progression-owned primary
 * enrollment: the column still says intake_course while the student is
 * actually deferred.
 */
function driftedDeferredStudent(object $ctx, string $code): Student
{
    $student = Student::factory()
        ->forCampus($ctx->campus)
        ->forProgram($ctx->program)
        ->state([
            'student_id' => $code,
            'full_name' => "Student {$code}",
            'email' => strtolower($code).'@example.com',
            'status' => 'intake_course',
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
        'enrollment_status' => 'deferred',
        'study_stage' => 'intake_course',
        'source_type' => 'test_program_enrollment',
        'source_id' => $student->id,
        'source_snapshot' => [],
        'materialized_at' => now(),
    ]);

    return $student;
}

function driftedDng(Student $student, Semester $semester): DngPaymentRequest
{
    return DngPaymentRequest::create([
        'student_id' => $student->id,
        'campus_code' => 'TEST',
        'student_code' => $student->student_id,
        'item_id' => 'ITEM-'.$student->student_id,
        'fee_type' => 'tuition',
        'description' => 'Tuition',
        'semester_id' => $semester->id,
        'due_date' => now()->subDay(),
        'amount' => 2_000_000,
        'status' => DngPaymentRequest::STATUS_PUSHED_TO_DNG,
    ]);
}

it('labels a lifecycle-exception row from the enrollment status, not the legacy column', function () {
    $student = driftedDeferredStudent($this, 'LBL001');
    driftedDng($student, $this->semester);

    $row = collect(app(ListLifecycleDueExceptionsQuery::class)->handle([])->items())
        ->firstWhere('student.id', $student->id);

    expect($row)->not->toBeNull()
        ->and($row['student']['status'])->toBe('deferred')
        ->and($row['student']['status_label'])->toBe('Deferred')
        ->and($row['student']['status_color'])->toBe('orange');
});

it('labels a DNG lifecycle row from the enrollment status, not the legacy column', function () {
    $student = driftedDeferredStudent($this, 'LBL002');
    driftedDng($student, $this->semester);

    $result = app(ListDngLifecycleQuery::class)->handle($this->semester->id);
    $row = collect($result['rows']->items())->firstWhere('student.id', $student->id);

    expect($row)->not->toBeNull()
        ->and($row['student']['status'])->toBe('deferred')
        ->and($row['student']['status_label'])->toBe('Deferred');
});
