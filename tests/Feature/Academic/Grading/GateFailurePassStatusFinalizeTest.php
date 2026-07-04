<?php

declare(strict_types=1);

use App\Models\AcademicRecord;
use App\Models\AssessmentComponent;
use App\Models\AssessmentComponentDetail;
use App\Models\AssessmentComponentDetailScore;
use App\Models\Attendance;
use App\Models\Campus;
use App\Models\ClassSession;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\Semester;
use App\Models\Student;
use App\Models\SyllabusTemplate;
use App\Models\Unit;
use App\Modules\Academic\Actions\MarkCourseOfferingCompletedAction;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The real finalize pipeline (MarkCourseOfferingCompletedAction ->
 * CourseCompletionService::finalizeCourse) derived `is_passed` from a flat
 * final_percentage-vs-threshold check even for custom-scheme records — so a
 * gate-fail student (high score on one component, fails a required gate on
 * another) got `is_passed = true` because the diagnostic 0-100 remap of the
 * scheme's FG sum stayed high. GradeDisplayPresenter.pass_status reads
 * is_passed directly, so this silently broke the cockpit, academic summary,
 * and student portal displays for any real gate-fail finalize — not just a
 * display bug, the stored record itself was wrong.
 */
it('marks a gate-fail student as not passed with zero credit despite a high diagnostic percentage', function () {
    $campus = Campus::factory()->create();
    app()->instance('campus', $campus);
    $semester = Semester::factory()->create();
    $unit = Unit::factory()->create();

    $syllabus = SyllabusTemplate::factory()->create([
        'unit_id' => $unit->id,
        'min_grade_threshold' => 60,
        'grading_scheme' => [
            'engine' => 'metropolia_v1',
            'scale' => '0-5',
            'components' => [
                ['code' => 'ASSIGNMENT', 'gate' => ['min_pct' => 40], 'conversion' => null],
                [
                    'code' => 'EXAM',
                    'gate' => ['min_pct' => 40],
                    'conversion' => ['type' => 'linear', 'min_pct' => 40, 'max_pct' => 88, 'min_grade' => 1, 'max_grade' => 5],
                ],
            ],
        ],
    ]);

    $offering = CourseOffering::factory()->create([
        'semester_id' => $semester->id,
        'unit_id' => $unit->id,
        'campus_id' => $campus->id,
        'syllabus_template_id' => $syllabus->id,
        'course_status' => 'in_progress',
        'enrollment_status' => 'open',
        'current_enrollment' => 0,
        'is_canvas_synced' => false,
    ]);

    $student = Student::factory()->forCampus($campus)->create([
        'status' => 'intake_course',
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $semester->id,
    ]);

    CourseRegistration::create([
        'student_id' => $student->id,
        'course_offering_id' => $offering->id,
        'semester_id' => $offering->semester_id,
        'registration_status' => 'confirmed',
        'registration_date' => now(),
        'registration_method' => 'admin_override',
        'credit_hours' => 3.0,
        'attempt_number' => 1,
        'is_retake' => false,
        'retake_fee' => 0.00,
        'is_retake_paid' => 'no',
    ]);

    AcademicRecord::factory()->create([
        'student_id' => $student->id,
        'course_offering_id' => $offering->id,
        'semester_id' => $offering->semester_id,
        'unit_id' => $unit->id,
        'campus_id' => $campus->id,
        'credit_hours' => 3.0,
        'credit_points' => 3.0,
        'enrollment_date' => now()->toDateString(),
    ]);

    $assignment = AssessmentComponent::factory()->create([
        'syllabus_template_id' => $syllabus->id,
        'code' => 'ASSIGNMENT',
        'weight' => 0,
    ]);
    $assignmentDetail = AssessmentComponentDetail::factory()->create([
        'assessment_component_id' => $assignment->id,
        'weight' => 1,
    ]);
    AssessmentComponentDetailScore::create([
        'student_id' => $student->id,
        'course_offering_id' => $offering->id,
        'assessment_component_detail_id' => $assignmentDetail->id,
        'percentage_score' => 30.0, // fails the 40% gate
        'score_status' => 'final',
        'score_excluded' => false,
    ]);

    $exam = AssessmentComponent::factory()->create([
        'syllabus_template_id' => $syllabus->id,
        'code' => 'EXAM',
        'weight' => 100,
    ]);
    $examDetail = AssessmentComponentDetail::factory()->create([
        'assessment_component_id' => $exam->id,
        'weight' => 1,
    ]);
    AssessmentComponentDetailScore::create([
        'student_id' => $student->id,
        'course_offering_id' => $offering->id,
        'assessment_component_detail_id' => $examDetail->id,
        'percentage_score' => 88.0, // strong score, but the gate still fails the course
        'score_status' => 'final',
        'score_excluded' => false,
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
    Attendance::create([
        'class_session_id' => $session->id,
        'student_id' => $student->id,
        'status' => 'present',
        'recording_method' => 'manual',
    ]);

    MarkCourseOfferingCompletedAction::run($offering->fresh());

    $record = AcademicRecord::where('student_id', $student->id)
        ->where('course_offering_id', $offering->id)
        ->first();

    expect($record->grade_breakdown['gates_passed'])->toBeFalse()
        ->and($record->grade_breakdown['final_grade'])->toBe('0')
        ->and($record->is_passed)->toBeFalse()
        ->and((float) $record->credit_points_earned)->toBe(0.0);
});
