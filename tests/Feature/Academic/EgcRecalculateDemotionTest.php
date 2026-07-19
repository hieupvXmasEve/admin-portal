<?php

declare(strict_types=1);

use App\Enums\AcademicProgressionEventType;
use App\Models\AcademicProgressionEvent;
use App\Models\AcademicRecord;
use App\Models\Attendance;
use App\Models\Campus;
use App\Models\ClassSession;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Unit;
use App\Modules\Academic\Actions\MarkCourseOfferingCompletedAction;
use App\Modules\Academic\Progression\Models\ProgramEnrollment;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Covers the EGC recalculate progression audit (issue 03) and its fix
 * (issue 09): a student promoted on the initial finalize whose grade is
 * later corrected to failing on recalculate must have gc_current_level
 * reverted, with a recorded reversal event — not silently left at a level
 * they no longer qualify for. Also covers the two audit scenarios that were
 * already safe (promotion-on-correction, idempotent re-recalculate), moved
 * here from the throwaway repro at
 * .scratch/course-offering-cockpit/egc-recalc-audit-repro-test.php.
 */
uses(RefreshDatabase::class);

function makeEgcRecalcOffering(object $context, int $unitLevel): CourseOffering
{
    $unit = Unit::factory()->create(['unit_type' => 'egc', 'level' => $unitLevel]);

    $offering = CourseOffering::factory()->create([
        'semester_id' => $context->semester->id,
        'unit_id' => $unit->id,
        'campus_id' => $context->campus->id,
        'course_status' => 'in_progress',
        'enrollment_status' => 'open',
        'current_enrollment' => 0,
        'is_canvas_synced' => false,
    ]);

    $session = ClassSession::factory()->create([
        'course_offering_id' => $offering->id,
        'status' => 'completed',
        'session_date' => '2026-01-15',
        'start_time' => '09:00:00',
        'end_time' => '11:00:00',
        'session_type' => 'lecture',
        'delivery_mode' => 'in_person',
    ]);

    $attendanceMarker = Student::factory()->forCampus($context->campus)->create([
        'status' => 'intake_course',
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $context->semester->id,
    ]);

    Attendance::create([
        'class_session_id' => $session->id,
        'student_id' => $attendanceMarker->id,
        'status' => 'present',
        'recording_method' => 'manual',
    ]);

    return $offering;
}

function makeEgcRecalcStudent(object $context, int $currentLevel): Student
{
    return Student::factory()->forCampus($context->campus)->create([
        'status' => 'intake_pre_uni_gc',
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $context->semester->id,
        'gc_current_level' => $currentLevel,
        'gc_total_levels' => 6,
    ]);
}

function registerEgcRecalcStudent(object $context, CourseOffering $offering, Student $student, float $finalPercentage): AcademicRecord
{
    CourseRegistration::create([
        'student_id' => $student->id,
        'course_offering_id' => $offering->id,
        'semester_id' => $context->semester->id,
        'registration_status' => 'registered',
        'registration_date' => now(),
        'credit_points' => 3,
        'credit_hours' => 3,
    ]);

    return AcademicRecord::factory()->create([
        'student_id' => $student->id,
        'course_offering_id' => $offering->id,
        'semester_id' => $context->semester->id,
        'unit_id' => $offering->unit_id,
        'campus_id' => $context->campus->id,
        'final_percentage' => $finalPercentage,
        'total_present' => 0,
        'total_late' => 0,
        'total_absences' => 0,
        'total_not_recorded' => 0,
        'total_class_sessions' => 0,
    ]);
}

beforeEach(function () {
    $this->campus = Campus::factory()->create();
    $this->semester = Semester::factory()->active()->create();
    app()->singleton('campus', fn () => $this->campus);
    $this->context = (object) ['campus' => $this->campus, 'semester' => $this->semester];
});

function egcEnrollmentLevel(Student $student): ?int
{
    return ProgramEnrollment::query()
        ->where('student_id', $student->id)
        ->where('is_primary', true)
        ->value('egc_current_level');
}

it('reverts the owned EGC level and records a reversal event when a corrected grade flips a promoted student to failing', function () {
    $offering = makeEgcRecalcOffering($this->context, unitLevel: 3);
    $student = makeEgcRecalcStudent($this->context, currentLevel: 3);
    $record = registerEgcRecalcStudent($this->context, $offering, $student, finalPercentage: 85);

    MarkCourseOfferingCompletedAction::run($offering, recalculate: false);
    $student->refresh();
    expect(egcEnrollmentLevel($student))->toBe(4)
        ->and($student->gc_current_level)->toBe(3);
    expect(AcademicProgressionEvent::where('student_id', $student->id)->count())->toBe(1);

    $record->update(['final_percentage' => 50]);
    $result = MarkCourseOfferingCompletedAction::run($offering, recalculate: true);
    $student->refresh();

    expect(egcEnrollmentLevel($student))->toBe(3)
        ->and($student->gc_current_level)->toBe(3)
        ->and($result['egc_progression']['failed_students'][0]['action'])->toContain('reverted');

    $events = AcademicProgressionEvent::where('student_id', $student->id)->orderBy('id')->get();
    expect($events)->toHaveCount(2);

    $reversal = $events->last();
    expect($reversal->event_type)->toBe(AcademicProgressionEventType::ENGLISH_LEVEL_CHANGED)
        ->and($reversal->from_english_level)->toBe(4)
        ->and($reversal->to_english_level)->toBe(3)
        ->and($reversal->notes)->toContain('Recalculate reversal');
});

it('does not revert the level when the student has progressed further via another course since', function () {
    $offering = makeEgcRecalcOffering($this->context, unitLevel: 3);
    $student = makeEgcRecalcStudent($this->context, currentLevel: 3);
    $record = registerEgcRecalcStudent($this->context, $offering, $student, finalPercentage: 85);

    MarkCourseOfferingCompletedAction::run($offering, recalculate: false);
    $student->refresh();
    expect(egcEnrollmentLevel($student))->toBe(4);

    // Student has since progressed to level 5 via an unrelated course.
    ProgramEnrollment::query()
        ->where('student_id', $student->id)
        ->where('is_primary', true)
        ->update(['egc_current_level' => 5]);

    $record->update(['final_percentage' => 50]);
    $result = MarkCourseOfferingCompletedAction::run($offering, recalculate: true);
    $student->refresh();

    // Exact-level-match guard: owned EGC level (5) no longer equals
    // unitLevel + 1 (4), so the demotion is skipped rather than clobbering
    // progress made elsewhere.
    expect(egcEnrollmentLevel($student))->toBe(5)
        ->and($student->gc_current_level)->toBe(3)
        ->and($result['egc_progression']['failed_students'][0]['action'])->toBe('Level unchanged (failed course)');

    expect(AcademicProgressionEvent::where('student_id', $student->id)->count())->toBe(1);
});

it('skips the revert and flags a conflict when the student already has a newer record for the next-level unit', function () {
    $offering = makeEgcRecalcOffering($this->context, unitLevel: 3);
    $student = makeEgcRecalcStudent($this->context, currentLevel: 3);
    $record = registerEgcRecalcStudent($this->context, $offering, $student, finalPercentage: 85);

    MarkCourseOfferingCompletedAction::run($offering, recalculate: false);
    $student->refresh();
    expect(egcEnrollmentLevel($student))->toBe(4);

    // Student is already enrolled in a level-4 unit elsewhere.
    $level4Unit = Unit::factory()->create(['unit_type' => 'egc', 'level' => 4]);
    $level4Offering = CourseOffering::factory()->create([
        'semester_id' => $this->semester->id,
        'unit_id' => $level4Unit->id,
        'campus_id' => $this->campus->id,
        'course_status' => 'in_progress',
    ]);
    AcademicRecord::factory()->create([
        'student_id' => $student->id,
        'course_offering_id' => $level4Offering->id,
        'semester_id' => $this->semester->id,
        'unit_id' => $level4Unit->id,
        'campus_id' => $this->campus->id,
        'completion_status' => 'in_progress',
        'final_letter_grade' => null,
    ]);

    $record->update(['final_percentage' => 50]);
    $result = MarkCourseOfferingCompletedAction::run($offering, recalculate: true);
    $student->refresh();

    expect(egcEnrollmentLevel($student))->toBe(4)
        ->and($result['egc_progression']['failed_students'][0]['action'])->toBe('Level unchanged (failed course)');

    // No reversal event recorded; only the original promotion event exists.
    expect(AcademicProgressionEvent::where('student_id', $student->id)->count())->toBe(1);
});

it('progresses the level exactly once when a corrected grade flips a failing student to passing', function () {
    $offering = makeEgcRecalcOffering($this->context, unitLevel: 3);
    $student = makeEgcRecalcStudent($this->context, currentLevel: 3);
    $record = registerEgcRecalcStudent($this->context, $offering, $student, finalPercentage: 50);

    MarkCourseOfferingCompletedAction::run($offering, recalculate: false);
    $student->refresh();
    expect(egcEnrollmentLevel($student))->toBe(3);
    expect(AcademicProgressionEvent::where('student_id', $student->id)->count())->toBe(0);

    $record->update(['final_percentage' => 85]);
    MarkCourseOfferingCompletedAction::run($offering, recalculate: true);
    $student->refresh();

    expect(egcEnrollmentLevel($student))->toBe(4)
        ->and(AcademicProgressionEvent::where('student_id', $student->id)->count())->toBe(1);
});

it('does not double-progress when re-running recalculate with an unchanged passing grade', function () {
    $offering = makeEgcRecalcOffering($this->context, unitLevel: 3);
    $student = makeEgcRecalcStudent($this->context, currentLevel: 3);
    registerEgcRecalcStudent($this->context, $offering, $student, finalPercentage: 85);

    MarkCourseOfferingCompletedAction::run($offering, recalculate: false);
    $student->refresh();
    expect(egcEnrollmentLevel($student))->toBe(4);

    MarkCourseOfferingCompletedAction::run($offering, recalculate: true);
    $student->refresh();

    expect(egcEnrollmentLevel($student))->toBe(4)
        ->and(AcademicProgressionEvent::where('student_id', $student->id)->count())->toBe(1);
});
