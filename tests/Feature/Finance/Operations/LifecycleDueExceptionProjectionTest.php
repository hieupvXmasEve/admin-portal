<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Academic\Progression\Models\ProgramEnrollment;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Queries\Operations\GetLifecycleDueExceptionSummaryQuery;
use App\Modules\Finance\Queries\Operations\ListLifecycleDueExceptionsQuery;
use App\Modules\Finance\Support\LifecycleDueExceptionReasonResolver;
use App\Modules\Finance\Support\LifecycleDueItemPredicate;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->campus = Campus::factory()->create();
    $this->semester = Semester::factory()->active()->create();
    $this->program = Program::factory()->create();

    session(['current_campus_id' => $this->campus->id]);
    app()->singleton('campus', fn () => $this->campus);
});

function projectionStudent(object $ctx, string $code, string $columnStatus): Student
{
    return Student::factory()
        ->forCampus($ctx->campus)
        ->forProgram($ctx->program)
        ->state([
            'student_id' => $code,
            'full_name' => "Student {$code}",
            'email' => strtolower($code).'@example.com',
            'status' => $columnStatus,
            'intake' => 1,
            'intake_mode' => 'sequential',
            'intake_semester_id' => $ctx->semester->id,
        ])
        ->create();
}

function projectionEnrollment(Student $student, string $enrollmentStatus, ?string $studyStage = null): ProgramEnrollment
{
    return ProgramEnrollment::create([
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
}

function projectionDue(Student $student, Semester $semester, string $code): DngPaymentRequest
{
    return DngPaymentRequest::create([
        'student_id' => $student->id,
        'campus_code' => 'TEST',
        'student_code' => $student->student_id,
        'item_id' => 'ITEM-'.$code,
        'fee_type' => 'tuition',
        'description' => 'Tuition',
        'semester_id' => $semester->id,
        'due_date' => now()->subDay(),
        'amount' => 2_000_000,
        'status' => DngPaymentRequest::STATUS_PUSHED_TO_DNG,
    ]);
}

it('excludes a drifted-to-financial student from the deferred bucket', function (): void {
    $student = projectionStudent($this, 'PRJEX001', 'deferred');
    projectionEnrollment($student, 'active', 'intake_course');
    projectionDue($student, $this->semester, 'PRJEX001');

    $summary = app(GetLifecycleDueExceptionSummaryQuery::class)->handle($this->semester->id);

    expect($summary['deferred_count'])->toBe(0)
        ->and($summary['total_count'])->toBe(0);
});

it('keeps a no-enrollment dropout student in the dropout bucket via fallback', function (): void {
    $student = projectionStudent($this, 'PRJEX002', 'dropout');
    projectionDue($student, $this->semester, 'PRJEX002');

    $summary = app(GetLifecycleDueExceptionSummaryQuery::class)->handle($this->semester->id);

    expect($summary['dropout_count'])->toBe(1)
        ->and($summary['total_count'])->toBe(1);
});

it('collapses a withdrawn enrollment to dropout, never transfer', function (): void {
    $student = projectionStudent($this, 'PRJEX003', 'dropout_transfer');
    projectionEnrollment($student, 'withdrawn');
    projectionDue($student, $this->semester, 'PRJEX003');

    $summary = app(GetLifecycleDueExceptionSummaryQuery::class)->handle($this->semester->id);

    expect($summary['transfer_count'])->toBe(0)
        ->and($summary['dropout_count'])->toBe(1);
});

it('keeps deferred, dropout, transfer, and other summing to the total on a mixed fixture', function (): void {
    $deferred = projectionStudent($this, 'PRJEX004', 'intake_course');
    projectionEnrollment($deferred, 'deferred');
    projectionDue($deferred, $this->semester, 'PRJEX004');

    $dropout = projectionStudent($this, 'PRJEX005', 'dropout');
    projectionDue($dropout, $this->semester, 'PRJEX005');

    $other = projectionStudent($this, 'PRJEX006', 'graduated');
    projectionDue($other, $this->semester, 'PRJEX006');

    $summary = app(GetLifecycleDueExceptionSummaryQuery::class)->handle($this->semester->id);

    expect($summary['deferred_count'] + $summary['dropout_count'] + $summary['transfer_count'] + $summary['other_count'])
        ->toBe($summary['total_count'])
        ->and($summary['total_count'])->toBe(3);
});

it('agrees the lifecycle-exception gate and the persisted reason on the same drifted status', function (): void {
    $student = projectionStudent($this, 'PRJEX007', 'intake_course');
    projectionEnrollment($student, 'deferred');
    $request = projectionDue($student, $this->semester, 'PRJEX007');
    $request->load('student');

    $isException = LifecycleDueItemPredicate::isLifecycleException($request->student);
    $reason = LifecycleDueExceptionReasonResolver::resolve($request->student);

    expect($isException)->toBeTrue()
        ->and($reason->value)->toBe('deferred');
});

it('filters the list query to the projected deferred bucket', function (): void {
    $student = projectionStudent($this, 'PRJEX008', 'intake_course');
    projectionEnrollment($student, 'deferred');
    projectionDue($student, $this->semester, 'PRJEX008');

    $notDeferred = projectionStudent($this, 'PRJEX009', 'dropout');
    projectionDue($notDeferred, $this->semester, 'PRJEX009');

    $result = app(ListLifecycleDueExceptionsQuery::class)->handle(['lifecycle_status' => 'deferred']);

    expect(collect($result->items())->pluck('student.student_code')->all())->toBe(['PRJEX008']);
});
