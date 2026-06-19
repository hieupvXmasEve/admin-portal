<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\ClassSession;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\ExamResitAttempt;
use App\Models\ExamResitSession;
use App\Models\ExamRoomSlot;
use App\Models\Room;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Modules\Academic\Actions\ScheduleExamResitAttemptAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->campus = Campus::factory()->create();
    $this->semester = Semester::factory()->create();
    $this->student = Student::factory()->forCampus($this->campus)->create([
        'status' => 'intake_course',
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $this->semester->id,
    ]);
    $this->room1 = Room::factory()->create(['campus_id' => $this->campus->id, 'capacity' => 30]);
    $this->room2 = Room::factory()->create(['campus_id' => $this->campus->id, 'capacity' => 30]);
});

/**
 * Build a scheduled room slot + a unit-scoped session inside it.
 *
 * @return array{0: ExamRoomSlot, 1: ExamResitSession}
 */
function slotWithSession(Unit $unit, array $slotOverrides = [], int $expectedCandidates = 5): array
{
    $slot = ExamRoomSlot::factory()->create(array_merge([
        'campus_id' => test()->campus->id,
        'room_id' => test()->room1->id,
        'exam_date' => '2026-07-01',
        'start_time' => '09:00:00',
        'end_time' => '11:00:00',
        'capacity' => 30,
    ], $slotOverrides));

    $session = ExamResitSession::factory()->create([
        'exam_room_slot_id' => $slot->id,
        'unit_id' => $unit->id,
        'semester_id' => test()->semester->id,
        'campus_id' => $slot->campus_id,
        'expected_candidates' => $expectedCandidates,
        'status' => ExamResitSession::STATUS_SCHEDULED,
    ]);

    return [$slot, $session];
}

/**
 * Approved exam-resit attempt for a given student + unit, paid by default.
 */
function approvedAttemptFor(Student $student, Unit $unit, array $overrides = []): ExamResitAttempt
{
    return makeApprovedExamResitAttempt(
        $student,
        test()->campus,
        test()->semester,
        array_merge([
            'unit' => $unit,
            'hq_fee_status' => ExamResitAttempt::HQ_FEE_PAID,
            'paid_at' => now(),
        ], $overrides),
    );
}

function runScheduleExamResit(int $attemptId, int $sessionId, array $extra = []): ExamResitAttempt
{
    return app(ScheduleExamResitAttemptAction::class)->run(array_merge([
        'attempt_id' => $attemptId,
        'exam_resit_session_id' => $sessionId,
    ], $extra));
}

it('schedules an approved paid attempt into a matching-unit session', function () {
    $unit = Unit::factory()->create();
    [$slot, $session] = slotWithSession($unit, expectedCandidates: 5);
    $attempt = approvedAttemptFor($this->student, $unit);

    $result = runScheduleExamResit($attempt->id, $session->id);

    expect($result->status)->toBe(ExamResitAttempt::STATUS_SCHEDULED)
        ->and($result->exam_resit_session_id)->toBe($session->id)
        ->and($result->scheduled_at)->not->toBeNull()
        ->and($result->scheduled_by_user_id)->toBe($this->user->id);

    $session->refresh();
    expect($session->actual_candidates)->toBe(1);
});

it('rejects scheduling when the session unit does not match the attempt unit', function () {
    $attemptUnit = Unit::factory()->create();
    $sessionUnit = Unit::factory()->create();
    [, $session] = slotWithSession($sessionUnit);
    $attempt = approvedAttemptFor($this->student, $attemptUnit);

    runScheduleExamResit($attempt->id, $session->id);
})->throws(ValidationException::class);

it('rejects scheduling an attempt that is not in the approved state', function () {
    $unit = Unit::factory()->create();
    [, $session] = slotWithSession($unit);
    $attempt = approvedAttemptFor($this->student, $unit, ['status' => ExamResitAttempt::STATUS_COMPLETED]);

    runScheduleExamResit($attempt->id, $session->id);
})->throws(ValidationException::class);

it('rejects scheduling into a cancelled session', function () {
    $unit = Unit::factory()->create();
    [, $session] = slotWithSession($unit);
    $session->update(['status' => ExamResitSession::STATUS_CANCELLED]);
    $attempt = approvedAttemptFor($this->student, $unit);

    runScheduleExamResit($attempt->id, $session->id);
})->throws(ValidationException::class);

it('rejects scheduling beyond the session expected candidate capacity', function () {
    $unit = Unit::factory()->create();
    [, $session] = slotWithSession($unit, expectedCandidates: 1);

    $first = approvedAttemptFor($this->student, $unit);
    runScheduleExamResit($first->id, $session->id);

    $otherStudent = Student::factory()->forCampus($this->campus)->create([
        'status' => 'intake_course',
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $this->semester->id,
    ]);
    $second = approvedAttemptFor($otherStudent, $unit);

    runScheduleExamResit($second->id, $session->id);
})->throws(ValidationException::class);

it('rejects scheduling a student who already has an overlapping exam-resit session', function () {
    $unit1 = Unit::factory()->create();
    $unit2 = Unit::factory()->create();

    [, $session1] = slotWithSession($unit1, ['room_id' => $this->room1->id, 'start_time' => '09:00:00', 'end_time' => '11:00:00']);
    [, $session2] = slotWithSession($unit2, ['room_id' => $this->room2->id, 'start_time' => '10:00:00', 'end_time' => '12:00:00']);

    $a1 = approvedAttemptFor($this->student, $unit1);
    runScheduleExamResit($a1->id, $session1->id);

    $a2 = approvedAttemptFor($this->student, $unit2);
    runScheduleExamResit($a2->id, $session2->id);
})->throws(ValidationException::class);

it('rejects scheduling a student who has an overlapping enrolled class session', function () {
    $unit = Unit::factory()->create();
    [, $session] = slotWithSession($unit, ['start_time' => '09:00:00', 'end_time' => '11:00:00']);

    $courseOffering = CourseOffering::factory()->create([
        'campus_id' => $this->campus->id,
        'semester_id' => $this->semester->id,
    ]);
    CourseRegistration::create([
        'student_id' => $this->student->id,
        'course_offering_id' => $courseOffering->id,
        'semester_id' => $this->semester->id,
        'registration_status' => 'registered',
        'registration_date' => now(),
        'credit_points' => 3,
        'credit_hours' => 3,
    ]);
    ClassSession::factory()->create([
        'course_offering_id' => $courseOffering->id,
        'room_id' => $this->room2->id,
        'session_date' => '2026-07-01',
        'start_time' => '10:00:00',
        'end_time' => '12:00:00',
        'status' => 'scheduled',
    ]);

    $attempt = approvedAttemptFor($this->student, $unit);

    runScheduleExamResit($attempt->id, $session->id);
})->throws(ValidationException::class);

it('blocks scheduling an unpaid attempt when policy forbids unpaid sitting', function () {
    $unit = Unit::factory()->create();
    [, $session] = slotWithSession($unit);
    $attempt = approvedAttemptFor($this->student, $unit, [
        'hq_fee_status' => ExamResitAttempt::HQ_FEE_CHARGE_CREATED,
        'paid_at' => null,
        'allow_unpaid_sitting_snapshot' => false,
    ]);

    runScheduleExamResit($attempt->id, $session->id);
})->throws(ValidationException::class);

it('allows scheduling an unpaid attempt with a recorded reason when policy permits', function () {
    $unit = Unit::factory()->create();
    [, $session] = slotWithSession($unit);
    $attempt = approvedAttemptFor($this->student, $unit, [
        'hq_fee_status' => ExamResitAttempt::HQ_FEE_CHARGE_CREATED,
        'paid_at' => null,
        'allow_unpaid_sitting_snapshot' => true,
    ]);

    $result = runScheduleExamResit($attempt->id, $session->id, [
        'unpaid_sitting_reason' => 'Academic approved sitting before payment',
    ]);

    expect($result->status)->toBe(ExamResitAttempt::STATUS_SCHEDULED)
        ->and($result->unpaid_allowed_reason)->toBe('Academic approved sitting before payment')
        ->and($result->unpaid_allowed_by_user_id)->toBe($this->user->id)
        ->and($result->unpaid_allowed_at)->not->toBeNull();
});

it('requires a reason when scheduling an unpaid attempt under unpaid policy', function () {
    $unit = Unit::factory()->create();
    [, $session] = slotWithSession($unit);
    $attempt = approvedAttemptFor($this->student, $unit, [
        'hq_fee_status' => ExamResitAttempt::HQ_FEE_CHARGE_CREATED,
        'paid_at' => null,
        'allow_unpaid_sitting_snapshot' => true,
    ]);

    runScheduleExamResit($attempt->id, $session->id);
})->throws(ValidationException::class);
