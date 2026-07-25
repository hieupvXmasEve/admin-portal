<?php

declare(strict_types=1);

use App\Models\AcademicRecord;
use App\Models\AssessmentComponent;
use App\Models\AssessmentComponentDetail;
use App\Models\AssessmentComponentDetailScore;
use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\CurriculumModule;
use App\Models\GpaCalculation;
use App\Models\Module;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Unit;
use App\Modules\Academic\Progression\Queries\GetStudentHubScoresQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Issue 03 — Scores & GPA read tab (the merged view).
 *
 * Locks the single Query-seam contract that backs the one combined tab:
 * per-course scores + assessment breakdown, GPA history, and academic
 * standing all arrive together, scoped to this one student.
 */

/**
 * Record one graded assessment for the student on a course offering, so the
 * offering surfaces in the scores breakdown.
 */
function recordAssessmentScore(Student $student, CourseOffering $offering, float $percentage): AssessmentComponentDetailScore
{
    $component = AssessmentComponent::factory()->create(['type' => 'exam', 'name' => 'Final Exam']);
    $detail = AssessmentComponentDetail::factory()->create([
        'assessment_component_id' => $component->id,
        'name' => 'Final Exam Paper',
    ]);

    return AssessmentComponentDetailScore::query()->create([
        'assessment_component_detail_id' => $detail->id,
        'student_id' => $student->id,
        'course_offering_id' => $offering->id,
        'points_earned' => $percentage,
        'percentage_score' => $percentage,
        'status' => 'graded',
        'graded_at' => now(),
    ]);
}

function seedScoredCourse(Student $student, Campus $campus, Semester $semester, float $percentage): CourseOffering
{
    $unit = Unit::factory()->create(['credit_points' => 3.0]);
    $offering = CourseOffering::factory()->create([
        'semester_id' => $semester->id,
        'unit_id' => $unit->id,
        'campus_id' => $campus->id,
    ]);

    AcademicRecord::factory()->create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'unit_id' => $unit->id,
        'program_id' => $student->program_id,
        'campus_id' => $campus->id,
        'course_offering_id' => $offering->id,
        'credit_points' => 3.0,
        'credit_points_earned' => 3.0,
        'final_percentage' => $percentage,
        'grade_status' => 'final',
        'excluded_from_gpa' => false,
        'is_passed' => true,
    ]);

    recordAssessmentScore($student, $offering, $percentage);

    return $offering;
}

it('merges per-course scores, assessment breakdown, GPA history and academic standing into one contract', function () {
    $campus = Campus::factory()->create();
    $sem1 = Semester::factory()->create(['code' => 'SUM2026-SG', 'name' => 'Summer 2026 SG', 'start_date' => now()->subMonths(8)]);
    $sem2 = Semester::factory()->create(['code' => 'FALL2026-SG', 'name' => 'Fall 2026 SG', 'start_date' => now()->subMonths(4)]);
    $student = Student::factory()->forCampus($campus)->create(['intake' => 1, 'intake_mode' => 'sequential', 'intake_semester_id' => Semester::factory()->create()->id]);

    $offering1 = seedScoredCourse($student, $campus, $sem1, 78.0);
    $offering2 = seedScoredCourse($student, $campus, $sem2, 88.0);
    $module = Module::create([
        'campus_id' => $campus->id,
        'code' => 'SCORES-MODULE',
        'name' => 'Scores Module',
        'grading_type' => 'grade',
        'total_credits' => 6,
    ]);
    $module->units()->attach([
        $offering1->unit_id => ['grading_type' => 'grade', 'weight' => 0, 'order' => 1],
        $offering2->unit_id => ['grading_type' => 'grade', 'weight' => 1, 'order' => 2],
    ]);
    CurriculumModule::query()->create([
        'curriculum_version_id' => $student->curriculum_version_id,
        'module_id' => $module->id,
        'year_level' => 1,
        'semester_number' => 1,
        'is_required' => true,
        'order' => 1,
    ]);

    GpaCalculation::create([
        'student_id' => $student->id,
        'semester_id' => $sem1->id,
        'program_id' => $student->program_id,
        'semester_gpa' => 78.0,
        'cumulative_gpa' => 78.0,
        'semester_credit_points' => 3.0,
        'cumulative_credit_points' => 3.0,
        'semester_credit_points_earned' => 3.0,
        'cumulative_credit_points_earned' => 3.0,
        'academic_standing' => 'normal',
        'is_finalized' => true,
        'is_current' => false,
    ]);
    GpaCalculation::create([
        'student_id' => $student->id,
        'semester_id' => $sem2->id,
        'program_id' => $student->program_id,
        'semester_gpa' => 88.0,
        'cumulative_gpa' => 83.0,
        'semester_credit_points' => 3.0,
        'cumulative_credit_points' => 6.0,
        'semester_credit_points_earned' => 3.0,
        'cumulative_credit_points_earned' => 6.0,
        'academic_standing' => 'normal',
        'is_finalized' => true,
        'is_current' => true,
    ]);

    $scores = app(GetStudentHubScoresQuery::class)->handle((int) $student->id);

    // Per-course scores with an assessment breakdown.
    $courses = collect($scores['standalone_units']['data']);
    expect($courses)->toHaveCount(2);

    $firstCourse = $courses->firstWhere('course_offering_id', $offering1->id);
    expect($firstCourse)->not->toBeNull()
        ->and($firstCourse['scores'])->toHaveCount(1)
        ->and($firstCourse['scores'][0]['assessment_name'])->toBe('Final Exam Paper')
        ->and($firstCourse['scores'][0]['assessment_type'])->toBe('exam')
        ->and((float) $firstCourse['course_average'])->toBe(78.0);

    // GPA history: one snapshot per finalized semester, carrying standing.
    expect($scores['semesters'])->toHaveCount(2)
        ->and($scores['semesters'][0]['academic_standing'])->toBe('normal');

    // Academic standing: cumulative pulled from the current GPA row.
    expect($scores['cumulative'])->not->toBeNull()
        ->and((float) $scores['cumulative']['gpa'])->toBe(83.0)
        ->and($scores['cumulative']['academic_standing'])->toBe('normal');

    expect($scores['modules']['data'])->toHaveCount(1)
        ->and((float) $scores['modules']['data'][0]['module_grade'])->toBe(88.0)
        ->and($scores['modules']['data'][0]['status'])->toBe('passed')
        ->and($scores['modules']['data'][0]['grading_info']['uses_weights'])->toBeTrue()
        ->and($scores['modules']['data'][0]['sub_units'][0]['code'])->toBe($offering1->unit->code)
        ->and($scores['modules']['data'][0]['sub_units'][1]['included_in_average'])->toBeTrue();
});

it('returns only this student grades and GPA, never another student', function () {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->create(['code' => 'FALL2026-SGISO', 'name' => 'Fall 2026 SG Iso']);
    $student = Student::factory()->forCampus($campus)->create(['intake' => 1, 'intake_mode' => 'sequential', 'intake_semester_id' => Semester::factory()->create()->id]);
    $other = Student::factory()->forCampus($campus)->create(['intake' => 1, 'intake_mode' => 'sequential', 'intake_semester_id' => Semester::factory()->create()->id]);

    $mine = seedScoredCourse($student, $campus, $semester, 70.0);
    $theirs = seedScoredCourse($other, $campus, $semester, 95.0);

    GpaCalculation::create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'program_id' => $student->program_id,
        'semester_gpa' => 70.0,
        'cumulative_gpa' => 70.0,
        'semester_credit_points' => 3.0,
        'cumulative_credit_points' => 3.0,
        'semester_credit_points_earned' => 3.0,
        'cumulative_credit_points_earned' => 3.0,
        'academic_standing' => 'normal',
        'is_finalized' => true,
        'is_current' => true,
    ]);
    GpaCalculation::create([
        'student_id' => $other->id,
        'semester_id' => $semester->id,
        'program_id' => $other->program_id,
        'semester_gpa' => 95.0,
        'cumulative_gpa' => 95.0,
        'semester_credit_points' => 3.0,
        'cumulative_credit_points' => 3.0,
        'semester_credit_points_earned' => 3.0,
        'cumulative_credit_points_earned' => 3.0,
        'academic_standing' => 'normal',
        'is_finalized' => true,
        'is_current' => true,
    ]);

    $scores = app(GetStudentHubScoresQuery::class)->handle((int) $student->id);
    $offeringIds = collect($scores['standalone_units']['data'])->pluck('course_offering_id');

    expect($offeringIds)->toContain($mine->id)
        ->and($offeringIds)->not->toContain($theirs->id)
        ->and($scores['standalone_units']['data'])->toHaveCount(1)
        ->and($scores['semesters'])->toHaveCount(1)
        ->and((float) $scores['cumulative']['gpa'])->toBe(70.0);
});
