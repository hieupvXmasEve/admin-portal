<?php

declare(strict_types=1);

use App\Models\AcademicRecord;
use App\Models\CourseOffering;
use App\Models\CurriculumUnit;
use App\Models\CurriculumVersion;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * Semester Progress on the student portal counted a finalized-but-failed
 * course as "completed", showing 100% progress for a student who failed
 * their only course. "Completed" must mean passed, matching the is_passed
 * convention already used by overall_summary.
 */
it('excludes a failed-but-finalized course from completed_units and semester progress', function () {
    $semester = Semester::factory()->create();
    $curriculumVersion = CurriculumVersion::factory()->create();
    $unit = Unit::factory()->create();

    CurriculumUnit::factory()->create([
        'curriculum_version_id' => $curriculumVersion->id,
        'unit_id' => $unit->id,
        'semester_id' => $semester->id,
        'semester_number' => 1,
    ]);

    $courseOffering = CourseOffering::factory()->create([
        'unit_id' => $unit->id,
        'semester_id' => $semester->id,
    ]);

    $student = Student::factory()->create([
        'curriculum_version_id' => $curriculumVersion->id,
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $semester->id,
    ]);

    AcademicRecord::factory()->create([
        'student_id' => $student->id,
        'unit_id' => $unit->id,
        'semester_id' => $semester->id,
        'course_offering_id' => $courseOffering->id,
        'completion_status' => 'completed',
        'grade_status' => 'final',
        'is_passed' => false,
        'credit_points_earned' => 0,
    ]);

    Sanctum::actingAs($student);

    $this->getJson(route('v1.student.grades.index'))
        ->assertOk()
        ->assertJsonPath('data.grades_by_semester.0.semester_summary.total_units', 1)
        ->assertJsonPath('data.grades_by_semester.0.semester_summary.completed_units', 0);
});

it('counts a passed and finalized course as completed', function () {
    $semester = Semester::factory()->create();
    $curriculumVersion = CurriculumVersion::factory()->create();
    $unit = Unit::factory()->create();

    CurriculumUnit::factory()->create([
        'curriculum_version_id' => $curriculumVersion->id,
        'unit_id' => $unit->id,
        'semester_id' => $semester->id,
        'semester_number' => 1,
    ]);

    $courseOffering = CourseOffering::factory()->create([
        'unit_id' => $unit->id,
        'semester_id' => $semester->id,
    ]);

    $student = Student::factory()->create([
        'curriculum_version_id' => $curriculumVersion->id,
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $semester->id,
    ]);

    AcademicRecord::factory()->create([
        'student_id' => $student->id,
        'unit_id' => $unit->id,
        'semester_id' => $semester->id,
        'course_offering_id' => $courseOffering->id,
        'completion_status' => 'completed',
        'grade_status' => 'final',
        'is_passed' => true,
        'credit_points_earned' => 3,
    ]);

    Sanctum::actingAs($student);

    $this->getJson(route('v1.student.grades.index'))
        ->assertOk()
        ->assertJsonPath('data.grades_by_semester.0.semester_summary.completed_units', 1);
});

it('counts every curriculum unit planned for the semester, not just ones the student has started', function () {
    $semester = Semester::factory()->create();
    $curriculumVersion = CurriculumVersion::factory()->create();
    $startedUnit = Unit::factory()->create();
    $notYetStartedUnit = Unit::factory()->create();

    CurriculumUnit::factory()->create([
        'curriculum_version_id' => $curriculumVersion->id,
        'unit_id' => $startedUnit->id,
        'semester_id' => $semester->id,
        'semester_number' => 1,
    ]);
    CurriculumUnit::factory()->create([
        'curriculum_version_id' => $curriculumVersion->id,
        'unit_id' => $notYetStartedUnit->id,
        'semester_id' => $semester->id,
        'semester_number' => 1,
    ]);

    $courseOffering = CourseOffering::factory()->create([
        'unit_id' => $startedUnit->id,
        'semester_id' => $semester->id,
    ]);

    $student = Student::factory()->create([
        'curriculum_version_id' => $curriculumVersion->id,
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $semester->id,
    ]);

    // Only the started unit has an academic record — the not-yet-started unit
    // has none at all (matches a real curriculum roadmap unit the student
    // hasn't enrolled in yet).
    AcademicRecord::factory()->create([
        'student_id' => $student->id,
        'unit_id' => $startedUnit->id,
        'semester_id' => $semester->id,
        'course_offering_id' => $courseOffering->id,
        'completion_status' => 'completed',
        'grade_status' => 'final',
        'is_passed' => true,
        'credit_points_earned' => 3,
    ]);

    Sanctum::actingAs($student);

    $this->getJson(route('v1.student.grades.index'))
        ->assertOk()
        ->assertJsonPath('data.grades_by_semester.0.curriculum_units', fn ($units) => count($units) === 2)
        ->assertJsonPath('data.grades_by_semester.0.semester_summary.total_units', 2)
        ->assertJsonPath('data.grades_by_semester.0.semester_summary.completed_units', 1);
});
