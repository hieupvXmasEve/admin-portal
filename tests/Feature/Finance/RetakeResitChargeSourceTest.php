<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\CourseRetakeRegistration;
use App\Models\Semester;
use App\Models\Student;
use App\Modules\Finance\Models\FinanceCharge;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createSourceGuardCharge(Student $student, Semester $semester, string $chargeType, string $sourceType, int $sourceId): FinanceCharge
{
    return FinanceCharge::create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'charge_type' => $chargeType,
        'amount' => 1_000_000,
        'description' => "Source guard {$chargeType}",
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
        'source_type' => $sourceType,
        'source_id' => $sourceId,
    ]);
}

it('blocks duplicate active retake charges for the same academic source', function () {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->create();
    $student = Student::factory()->forCampus($campus)->create([
        'status' => 'intake_course',
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $semester->id,
    ]);

    createSourceGuardCharge(
        $student,
        $semester,
        FinanceCharge::TYPE_RETAKE_FEE,
        CourseRetakeRegistration::class,
        123,
    );

    expect(fn () => createSourceGuardCharge(
        $student,
        $semester,
        FinanceCharge::TYPE_RETAKE_FEE,
        CourseRetakeRegistration::class,
        123,
    ))->toThrow(QueryException::class);
});

it('allows a new active source charge after the previous source charge is voided', function () {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->create();
    $student = Student::factory()->forCampus($campus)->create([
        'status' => 'intake_course',
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $semester->id,
    ]);

    $voided = createSourceGuardCharge(
        $student,
        $semester,
        FinanceCharge::TYPE_EXAM_RESIT_FEE,
        'App\\Models\\ExamResitAttempt',
        456,
    );
    $voided->update([
        'status' => FinanceCharge::STATUS_VOID,
        'voided_at' => now(),
        'void_reason' => 'replaced',
    ]);

    $replacement = createSourceGuardCharge(
        $student,
        $semester,
        FinanceCharge::TYPE_EXAM_RESIT_FEE,
        'App\\Models\\ExamResitAttempt',
        456,
    );

    expect($replacement->status)->toBe(FinanceCharge::STATUS_ACTIVE);
});
