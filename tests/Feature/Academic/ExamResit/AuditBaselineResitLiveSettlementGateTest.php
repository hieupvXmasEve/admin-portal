<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\ExamResitAttempt;
use App\Models\ExamResitSession;
use App\Models\ExamRoomSlot;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Modules\Academic\Delivery\Actions\CompleteExamResitAttemptAction;
use App\Modules\Academic\Delivery\Actions\ScheduleExamResitAttemptAction;
use App\Modules\Facilities\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class)->group('audit-baseline');

it('schedules and completes a ledger-settled resit whose Academic projection is still unpaid (P-03)', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $campus = Campus::factory()->create();
    $semester = Semester::factory()->create();
    $student = Student::factory()->forCampus($campus)->create([
        'status' => 'intake_course',
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $semester->id,
    ]);
    $unit = Unit::factory()->create();
    $room = Room::factory()->create(['campus_id' => $campus->id, 'capacity' => 30]);

    $attempt = makeApprovedExamResitAttempt($student, $campus, $semester, [
        'unit' => $unit,
        'allow_unpaid_sitting_snapshot' => false,
    ]);
    $attempt = settleExamResitAttemptLedger($attempt);

    expect($attempt->hq_fee_status)->toBe(ExamResitAttempt::HQ_FEE_CHARGE_CREATED);

    $slot = ExamRoomSlot::factory()->create([
        'campus_id' => $campus->id,
        'room_id' => $room->id,
        'exam_date' => '2026-07-01',
        'start_time' => '09:00:00',
        'end_time' => '11:00:00',
        'capacity' => 30,
    ]);
    $session = ExamResitSession::factory()->create([
        'exam_room_slot_id' => $slot->id,
        'unit_id' => $unit->id,
        'semester_id' => $semester->id,
        'campus_id' => $campus->id,
        'expected_candidates' => 5,
        'status' => ExamResitSession::STATUS_SCHEDULED,
    ]);

    $result = app(ScheduleExamResitAttemptAction::class)->run([
        'attempt_id' => $attempt->id,
        'exam_resit_session_id' => $session->id,
    ]);

    expect($result->status)->toBe(ExamResitAttempt::STATUS_SCHEDULED)
        ->and($result->exam_resit_session_id)->toBe($session->id);

    $completed = app(CompleteExamResitAttemptAction::class)->run([
        'attempt_id' => $result->id,
        'resit_score' => 75,
    ]);

    expect($completed->status)->toBe(ExamResitAttempt::STATUS_COMPLETED);
});
