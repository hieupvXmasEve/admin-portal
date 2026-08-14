<?php

declare(strict_types=1);

use App\Models\AcademicRecord;
use App\Models\Attendance;
use App\Models\Campus;
use App\Models\ClassSession;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\CurriculumVersion;
use App\Models\EgcBlock;
use App\Models\Lecture;
use App\Modules\Facilities\Models\Room;
use App\Models\Semester;
use App\Models\Student;
use App\Models\SyllabusTemplate;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{
 *     campus: Campus,
 *     semester: Semester,
 *     student: Student,
 *     unit: Unit,
 *     offering: CourseOffering,
 *     record: AcademicRecord,
 *     block: EgcBlock
 * }
 */
function seedEgcAttendanceFailureScenario(
    bool $activeSemester = true,
    float $finalPercentage = 75.0,
    ?string $unitCode = null,
    string $studentStatus = 'intake_pre_uni_gc',
): array {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->active()->create([
        'is_active' => $activeSemester,
    ]);

    $intakeSemester = Semester::factory()->create();
    $curriculumVersion = CurriculumVersion::factory()->state(['semester_id' => $intakeSemester->id])->create();

    $student = Student::factory()->forCampus($campus)->create([
        'curriculum_version_id' => $curriculumVersion->id,
        'intake_semester_id' => $intakeSemester->id,
        'intake' => 1,
        'intake_mode' => 'sequential',
        'status' => $studentStatus,
    ]);

    $unit = Unit::factory()->state([
        'unit_type' => 'egc',
        'level' => 3,
        'code' => $unitCode ?? 'EGC3-'.uniqid(),
    ])->create();

    $syllabus = SyllabusTemplate::factory()->create([
        'unit_id' => $unit->id,
        'min_grade_threshold' => 70.00,
        'min_attendance_threshold' => 80.00,
    ]);

    $offeringAttributes = CourseOffering::factory()->state([
        'semester_id' => $semester->id,
        'unit_id' => $unit->id,
        'campus_id' => $campus->id,
        'syllabus_template_id' => $syllabus->id,
        'grading_type' => 'grade',
    ])->raw();

    unset($offeringAttributes['drop_deadline'], $offeringAttributes['withdrawal_deadline']);

    $offering = CourseOffering::query()->create($offeringAttributes);

    CourseRegistration::query()->create([
        'student_id' => $student->id,
        'course_offering_id' => $offering->id,
        'semester_id' => $semester->id,
        'registration_status' => 'confirmed',
        'registration_date' => now(),
        'registration_method' => 'admin_override',
        'credit_hours' => 3,
        'attempt_number' => 1,
        'is_retake' => false,
        'retake_fee' => 0.00,
        'is_retake_paid' => 'no',
    ]);

    $lecture = Lecture::factory()->create();
    $room = Room::factory()->create([
        'campus_id' => $campus->id,
        'code' => 'R-'.uniqid(),
    ]);

    $sessions = collect(range(1, 10))->map(function (int $sequence) use ($offering, $lecture, $room): ClassSession {
        return ClassSession::factory()->create([
            'course_offering_id' => $offering->id,
            'lecture_id' => $lecture->id,
            'room_id' => $room->id,
            'sequence_number' => $sequence,
            'status' => 'completed',
            'session_date' => now()->subDays(20 - $sequence)->toDateString(),
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
            'expected_attendees' => 1,
        ]);
    });

    foreach ($sessions->take(5) as $session) {
        Attendance::query()->create([
            'class_session_id' => $session->id,
            'student_id' => $student->id,
            'status' => 'present',
            'recording_method' => 'manual',
        ]);
    }

    foreach ($sessions->skip(5) as $session) {
        Attendance::query()->create([
            'class_session_id' => $session->id,
            'student_id' => $student->id,
            'status' => 'absent',
            'recording_method' => 'manual',
        ]);
    }

    $record = AcademicRecord::factory()->create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'unit_id' => $unit->id,
        'course_offering_id' => $offering->id,
        'program_id' => $student->program_id,
        'campus_id' => $campus->id,
        'final_percentage' => $finalPercentage,
        'grade_status' => 'final',
        'completion_status' => 'completed',
        'is_passed' => false,
        'override_pass' => false,
        'failure_reason' => AcademicRecord::FAILURE_ATTENDANCE_FAILED,
        'attendance_percentage' => 50.00,
        'meets_attendance_requirement' => false,
        'total_present' => 5,
        'total_absences' => 5,
        'total_late' => 0,
        'total_not_recorded' => 0,
        'total_class_sessions' => 10,
        'credit_points' => 10,
        'credit_hours' => 3,
        'credit_points_earned' => 0,
        'credit_hours_earned' => 0,
    ]);

    $block = EgcBlock::factory()->create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'block_number' => 1,
        'level_number' => 3,
        'result' => EgcBlock::RESULT_FAIL,
        'attendance_rate' => 50.00,
    ]);

    return compact('campus', 'semester', 'student', 'unit', 'offering', 'record', 'block');
}

it('dry run reports remediation without writing', function () {
    $scenario = seedEgcAttendanceFailureScenario();

    $this->artisan('academic:remediate-egc-attendance-failures', ['--dry-run' => true])
        ->assertSuccessful();

    expect($scenario['record']->refresh()->is_passed)->toBeFalse()
        ->and(Attendance::query()->where('student_id', $scenario['student']->id)->where('status', 'absent')->count())->toBe(5);
});

it('remediates grade-pass attendance-fail EGC records in the active semester', function () {
    $scenario = seedEgcAttendanceFailureScenario();

    $this->artisan('academic:remediate-egc-attendance-failures')
        ->assertSuccessful();

    $record = $scenario['record']->refresh();
    $block = $scenario['block']->refresh();

    expect($record->is_passed)->toBeTrue()
        ->and($record->failure_reason)->toBeNull()
        ->and($record->meets_attendance_requirement)->toBeTrue()
        ->and((float) $record->attendance_percentage)->toBe(80.0)
        ->and($record->total_present)->toBe(8)
        ->and($record->total_absences)->toBe(2)
        ->and(Attendance::query()->where('student_id', $scenario['student']->id)->where('status', 'absent')->count())->toBe(2)
        ->and($block->result)->toBe(EgcBlock::RESULT_PASS)
        ->and((float) $block->attendance_rate)->toBe(80.0);
});

it('skips records in inactive semesters', function () {
    $scenario = seedEgcAttendanceFailureScenario(activeSemester: false);

    $this->artisan('academic:remediate-egc-attendance-failures')
        ->assertSuccessful();

    expect($scenario['record']->refresh()->is_passed)->toBeFalse();
});

it('skips records that fail the grade threshold', function () {
    $scenario = seedEgcAttendanceFailureScenario(finalPercentage: 55.0);

    $this->artisan('academic:remediate-egc-attendance-failures')
        ->assertSuccessful();

    expect($scenario['record']->refresh()->is_passed)->toBeFalse()
        ->and(Attendance::query()->where('student_id', $scenario['student']->id)->where('status', 'absent')->count())->toBe(5);
});

it('remediates students who are inactive on the class roster without violating session constraints', function () {
    $scenario = seedEgcAttendanceFailureScenario(studentStatus: 'deferred');
    $session = ClassSession::query()
        ->where('course_offering_id', $scenario['offering']->id)
        ->first();

    expect($session)->not->toBeNull()
        ->and($session->expected_attendees)->toBeGreaterThan(0);

    $this->artisan('academic:remediate-egc-attendance-failures')
        ->assertSuccessful();

    $session->refresh();

    expect($scenario['record']->refresh()->is_passed)->toBeTrue()
        ->and($session->expected_attendees)->toBeGreaterThan(0);
});

it('limits remediation to a single student when student-id is provided', function () {
    $scenario = seedEgcAttendanceFailureScenario();
    $other = seedEgcAttendanceFailureScenario();

    $this->artisan('academic:remediate-egc-attendance-failures', [
        '--student-id' => $scenario['student']->student_id,
    ])->assertSuccessful();

    expect($scenario['record']->refresh()->is_passed)->toBeTrue()
        ->and($other['record']->refresh()->is_passed)->toBeFalse();
});
