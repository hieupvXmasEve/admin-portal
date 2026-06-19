<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Finance\Actions\CreateExamResitChargeSimpleAction;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->campus = Campus::factory()->create(['dng_code' => 'FAUHN']);
    $this->semester = Semester::factory()->active()->create();

    app()->singleton('campus', fn () => $this->campus);
});

function worklistStudent(string $code): Student
{
    return Student::factory()->forCampus(test()->campus)->create([
        'student_id' => $code,
        'full_name' => "Student {$code}",
        'status' => 'intake_course',
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => test()->semester->id,
    ]);
}

it('surfaces approved exam-resit attempts without a charge in the PTL worklist', function () {
    $student = worklistStudent('PTL-SRC');
    $attempt = makeApprovedExamResitAttempt($student, $this->campus, $this->semester, [
        'fee_amount' => 750_000,
    ]);

    $result = runExamResitDngWorklist();
    $row = collect($result['students']->items())->first();

    expect($row['student_code'])->toBe('PTL-SRC')
        ->and($row['charge_count'])->toBe(0)
        ->and($row['balance'])->toBe(750_000.0)
        ->and($row['needs_charge_creation'])->toBeTrue()
        ->and($row['pending_registrations'])->toHaveCount(1)
        ->and($row['pending_registrations'][0]['id'])->toBe($attempt->id)
        ->and((float) $row['pending_registrations'][0]['retake_fee'])->toBe(750_000.0);
});

it('shows the exam-resit charge as a normal PTL worklist row once HQ creates it', function () {
    $this->actingAs(User::factory()->create());

    $student = worklistStudent('PTL-CHG');
    $attempt = makeApprovedExamResitAttempt($student, $this->campus, $this->semester, [
        'fee_amount' => 750_000,
    ]);

    app(CreateExamResitChargeSimpleAction::class)->handle(['attempt_id' => $attempt->id]);

    $result = runExamResitDngWorklist();
    $row = collect($result['students']->items())->first();

    expect($row['charge_count'])->toBe(1)
        ->and($row['balance'])->toBe(750_000.0)
        ->and($row['needs_charge_creation'])->toBeFalse();
});

it('does not surface exam-resit attempts in the HL worklist', function () {
    makeApprovedExamResitAttempt(worklistStudent('PTL-ONLY'), $this->campus, $this->semester);

    $result = runExamResitDngWorklist(['dng_fee_type' => 'HL']);

    expect($result['students']->total())->toBe(0);
});
