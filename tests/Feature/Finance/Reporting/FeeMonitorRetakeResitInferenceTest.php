<?php

declare(strict_types=1);

use App\Models\AcademicRecord;
use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\CourseRetakeRegistration;
use App\Models\FinanceCharge;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Modules\Finance\Actions\CreateExamResitChargeSimpleAction;
use App\Modules\Finance\Queries\Reporting\ListFeeMonitorQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->campus = Campus::factory()->create();
    $this->semester = Semester::factory()->create();
    $this->student = Student::factory()->forCampus($this->campus)->create([
        'student_id' => 'FM-RR-1', 'status' => 'intake_course', 'intake' => 1,
        'intake_mode' => 'sequential', 'intake_semester_id' => $this->semester->id,
    ]);
    app()->instance('campus', $this->campus);
});

function feeMonitorRetakeReg(Student $student, Campus $campus, Semester $semester, string $status = 'approved', array $overrides = []): CourseRetakeRegistration
{
    $unit = Unit::factory()->create();
    $offering = CourseOffering::factory()->create(['semester_id' => $semester->id, 'unit_id' => $unit->id, 'campus_id' => $campus->id]);
    $record = AcademicRecord::factory()->create([
        'student_id' => $student->id, 'campus_id' => $campus->id, 'semester_id' => $semester->id,
        'unit_id' => $unit->id, 'course_offering_id' => $offering->id,
        'completion_status' => 'failed', 'grade_status' => 'final', 'is_passed' => false,
    ]);

    return CourseRetakeRegistration::create(array_merge([
        'student_id' => $student->id,
        'unit_id' => $unit->id,
        'original_academic_record_id' => $record->id,
        'course_offering_id' => $offering->id,
        'semester_id' => $semester->id,
        'charge_semester_id' => $semester->id,
        'campus_id' => $campus->id,
        'status' => $status,
        'attempt_number' => 2,
        'retake_fee' => 5_000_000,
        'approved_at' => now(),
    ], $overrides));
}

function feeMonitorRows(int $semesterId, array $filters = []): Collection
{
    return app(ListFeeMonitorQuery::class)->collectRows($semesterId, $filters);
}

it('infers a missing course_retake row for an approved registration with no charge', function () {
    feeMonitorRetakeReg($this->student, $this->campus, $this->semester);

    $rows = feeMonitorRows($this->semester->id);
    $retake = $rows->firstWhere(fn ($r) => $r['expected_source'] === 'course_retake' && $r['student']['id'] === $this->student->id);

    expect($retake)->not->toBeNull()
        ->and($retake['generation_state'])->toBe('missing')
        ->and($retake['expected_fee_type'])->toBe(FinanceCharge::TYPE_RETAKE_FEE);
});

it('infers a missing exam_resit row for an approved attempt with no charge', function () {
    makeApprovedExamResitAttempt($this->student, $this->campus, $this->semester);

    $rows = feeMonitorRows($this->semester->id);
    $resit = $rows->firstWhere(fn ($r) => $r['expected_source'] === 'exam_resit' && $r['student']['id'] === $this->student->id);

    expect($resit)->not->toBeNull()
        ->and($resit['generation_state'])->toBe('missing')
        ->and($resit['expected_fee_type'])->toBe(FinanceCharge::TYPE_EXAM_RESIT_FEE);
});

it('shows generated (not a duplicate missing) when an exam_resit charge already exists', function () {
    $attempt = makeApprovedExamResitAttempt($this->student, $this->campus, $this->semester);
    app(CreateExamResitChargeSimpleAction::class)->handle(['attempt_id' => $attempt->id]);

    $rows = feeMonitorRows($this->semester->id);
    $resitRows = $rows->filter(fn ($r) => $r['expected_source'] === 'exam_resit' && $r['student']['id'] === $this->student->id)->values();

    expect($resitRows)->toHaveCount(1)
        ->and($resitRows[0]['generation_state'])->toBe('generated');
});

it('does not infer a missing row for a cancelled retake registration', function () {
    feeMonitorRetakeReg($this->student, $this->campus, $this->semester, CourseRetakeRegistration::STATUS_CANCELLED, [
        'cancelled_at' => now(), 'cancellation_reason' => 'x',
    ]);

    $rows = feeMonitorRows($this->semester->id);
    $retake = $rows->firstWhere(fn ($r) => $r['expected_source'] === 'course_retake' && $r['student']['id'] === $this->student->id);

    expect($retake)->toBeNull();
});
