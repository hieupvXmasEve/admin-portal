<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\ExamResitAttempt;
use App\Models\ExamResitSession;
use App\Models\ExamRoomSlot;
use App\Models\Room;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * @return array{0: Semester, 1: Campus, 2: ExamResitSession, 3: Unit}
 */
function examResitTimetableFixture(): array
{
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->active()->create([
        'start_date' => '2026-03-01',
        'end_date' => '2026-03-31',
    ]);
    $unit = Unit::factory()->create();
    $room = Room::factory()->create(['campus_id' => $campus->id, 'capacity' => 30]);

    $slot = ExamRoomSlot::factory()->create([
        'campus_id' => $campus->id,
        'room_id' => $room->id,
        'exam_date' => '2026-03-18', // Wednesday
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

    return [$semester, $campus, $session, $unit];
}

it('merges an assigned exam-resit sitting into the student weekly timetable', function () {
    [$semester, $campus, $session, $unit] = examResitTimetableFixture();

    $student = Student::factory()->forCampus($campus)->create([
        'status' => 'intake_course', 'intake' => 1, 'intake_mode' => 'sequential', 'intake_semester_id' => $semester->id,
    ]);
    Sanctum::actingAs($student);

    makeApprovedExamResitAttempt($student, $campus, $semester, [
        'unit' => $unit,
        'status' => ExamResitAttempt::STATUS_SCHEDULED,
        'exam_resit_session_id' => $session->id,
        'scheduled_at' => now(),
        'hq_fee_status' => ExamResitAttempt::HQ_FEE_PAID,
        'paid_at' => now(),
    ]);

    $response = $this->getJson(route('v1.student.timetable.index', [
        'semester_id' => $semester->id,
        'week_start' => '2026-03-16',
        'week_end' => '2026-03-22',
    ]));

    $response->assertSuccessful();
    $response->assertJsonPath('data.weekly_schedule.wednesday.exam_resits.0.unit.code', $unit->code);
    $response->assertJsonPath('data.weekly_schedule.wednesday.exam_resits.0.item_type', 'exam_resit');
    $response->assertJsonPath('data.weekly_schedule.wednesday.exam_resits.0.exam_date', '2026-03-18');
    $response->assertJsonPath('data.weekly_schedule.wednesday.exam_resits.0.payment_status', 'paid');
});

it('does not show a cancelled exam-resit sitting in the student weekly timetable', function () {
    [$semester, $campus, $session, $unit] = examResitTimetableFixture();

    $student = Student::factory()->forCampus($campus)->create([
        'status' => 'intake_course', 'intake' => 1, 'intake_mode' => 'sequential', 'intake_semester_id' => $semester->id,
    ]);
    Sanctum::actingAs($student);

    makeApprovedExamResitAttempt($student, $campus, $semester, [
        'unit' => $unit,
        'status' => ExamResitAttempt::STATUS_CANCELLED,
        'exam_resit_session_id' => $session->id,
        'scheduled_at' => now(),
        'cancelled_at' => now(),
        'hq_fee_status' => ExamResitAttempt::HQ_FEE_PAID,
        'paid_at' => now(),
    ]);

    $response = $this->getJson(route('v1.student.timetable.index', [
        'semester_id' => $semester->id,
        'week_start' => '2026-03-16',
        'week_end' => '2026-03-22',
    ]));

    $response->assertSuccessful();
    $response->assertJsonPath('data.weekly_schedule.wednesday.exam_resits', []);
});

it('does not show the exam-resit sitting to a student who is not assigned', function () {
    [$semester, $campus, $session, $unit] = examResitTimetableFixture();

    // The session exists, but THIS student has no attempt assigned to it.
    $otherStudent = Student::factory()->forCampus($campus)->create([
        'status' => 'intake_course', 'intake' => 1, 'intake_mode' => 'sequential', 'intake_semester_id' => $semester->id,
    ]);
    Sanctum::actingAs($otherStudent);

    $response = $this->getJson(route('v1.student.timetable.index', [
        'semester_id' => $semester->id,
        'week_start' => '2026-03-16',
        'week_end' => '2026-03-22',
    ]));

    $response->assertSuccessful();
    $response->assertJsonPath('data.weekly_schedule.wednesday.exam_resits', []);
});
