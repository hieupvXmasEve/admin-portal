<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Modules\Academic\Progression\Models\ProgramEnrollment;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Queries\Operations\ListUnresolvedSurplusQuery;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function unresolvedSurplusStudent(Campus $campus, Program $program, Semester $semester, string $code): Student
{
    return Student::factory()
        ->forCampus($campus)
        ->forProgram($program)
        ->state([
            'student_id' => $code,
            'email' => strtolower($code).'@example.com',
            'intake' => 1,
            'intake_semester_id' => $semester->id,
        ])
        ->create();
}

function unresolvedSurplusEnrollment(Student $student, string $enrollmentStatus, ?string $studyStage = null): void
{
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
}

function unresolvedSurplusPayment(Student $student, float $amount = 100000): Payment
{
    return Payment::query()->create([
        'student_id' => $student->id,
        'amount' => $amount,
        'method' => Payment::METHOD_GATEWAY,
        'source' => 'dng',
        'paid_at' => now(),
        'status' => Payment::STATUS_COMPLETED,
    ]);
}

beforeEach(function () {
    $this->campus = Campus::factory()->create();
    $this->otherCampus = Campus::factory()->create();
    $this->program = Program::factory()->create();
    $this->semester = Semester::factory()->create();
});

it('lists leftover cash only for students who have left school', function () {
    $leaver = unresolvedSurplusStudent($this->campus, $this->program, $this->semester, 'LEFT001');
    $enrolled = unresolvedSurplusStudent($this->campus, $this->program, $this->semester, 'ENR001');
    unresolvedSurplusEnrollment($leaver, 'graduated');
    unresolvedSurplusEnrollment($enrolled, 'active', 'intake_course');
    unresolvedSurplusPayment($leaver);
    unresolvedSurplusPayment($enrolled);

    $rows = app(ListUnresolvedSurplusQuery::class)->handle((int) $this->campus->id);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['student_id'])->toBe((int) $leaver->id)
        ->and($rows[0]['enrollment_status'])->toBe('graduated')
        ->and($rows[0]['unapplied'])->toBe(100000.0)
        ->and($rows[0]['work_type'])->toBe(ListUnresolvedSurplusQuery::WORK_TYPE);
});

it('does not list deferred students who still hold surplus', function () {
    $deferred = unresolvedSurplusStudent($this->campus, $this->program, $this->semester, 'DEF001');
    unresolvedSurplusEnrollment($deferred, 'deferred');
    unresolvedSurplusPayment($deferred);

    expect(app(ListUnresolvedSurplusQuery::class)->handle((int) $this->campus->id))->toBe([]);
});

it('hides another campus leftover from the current campus queue', function () {
    $home = unresolvedSurplusStudent($this->campus, $this->program, $this->semester, 'HOME001');
    $away = unresolvedSurplusStudent($this->otherCampus, $this->program, $this->semester, 'AWAY001');
    unresolvedSurplusEnrollment($home, 'dropout');
    unresolvedSurplusEnrollment($away, 'dropout');
    unresolvedSurplusPayment($home, 20000);
    unresolvedSurplusPayment($away, 30000);

    $homeRows = app(ListUnresolvedSurplusQuery::class)->handle((int) $this->campus->id);
    $awayRows = app(ListUnresolvedSurplusQuery::class)->handle((int) $this->otherCampus->id);

    expect($homeRows)->toHaveCount(1)
        ->and($homeRows[0]['student_id'])->toBe((int) $home->id)
        ->and($awayRows)->toHaveCount(1)
        ->and($awayRows[0]['student_id'])->toBe((int) $away->id);
});

it('rejects a missing campus unless the caller can view every campus', function () {
    $leaver = unresolvedSurplusStudent($this->campus, $this->program, $this->semester, 'ALL001');
    unresolvedSurplusEnrollment($leaver, 'dropout_transfer');
    unresolvedSurplusPayment($leaver);

    expect(fn () => app(ListUnresolvedSurplusQuery::class)->handle(null))
        ->toThrow(AuthorizationException::class);

    $rows = app(ListUnresolvedSurplusQuery::class)->handle(null, true);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['student_id'])->toBe((int) $leaver->id);
});
