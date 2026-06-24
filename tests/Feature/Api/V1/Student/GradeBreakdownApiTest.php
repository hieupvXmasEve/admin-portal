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
 * Create a student enrolled in a single curriculum unit and return the pieces
 * needed to attach an academic record for that unit.
 *
 * @return array{0: Student, 1: Unit, 2: Semester, 3: CourseOffering}
 */
function gradeBreakdownFixture(): array
{
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

    return [$student, $unit, $semester, $courseOffering];
}

it('exposes a custom-scheme grade_display object on student curriculum grades', function () {
    [$student, $unit, $semester, $courseOffering] = gradeBreakdownFixture();

    AcademicRecord::factory()->create([
        'student_id' => $student->id,
        'unit_id' => $unit->id,
        'semester_id' => $semester->id,
        'course_offering_id' => $courseOffering->id,
        'final_percentage' => 100,
        'final_letter_grade' => '5',
        'grade_points' => 5,
        'is_passed' => true,
        'grade_breakdown' => [
            'engine' => 'metropolia_v1',
            'scale' => '0-5',
            'components' => [
                'EXAM' => ['score_pct' => 88, 'converted_grade' => 5, 'gate_met' => null],
            ],
            'fg_sum_raw' => 5.0,
            'fg_rounded' => 5,
            'gates_passed' => true,
            'gate_failures' => [],
            'final_grade' => '5',
        ],
    ]);

    Sanctum::actingAs($student);

    $this->getJson(route('v1.student.grades.index'))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.grades_by_semester.0.curriculum_units.0.grade_display.scheme_engine', 'metropolia_v1')
        ->assertJsonPath('data.grades_by_semester.0.curriculum_units.0.grade_display.scale', 'numeric_0_5')
        ->assertJsonPath('data.grades_by_semester.0.curriculum_units.0.grade_display.final_label', '5')
        ->assertJsonPath('data.grades_by_semester.0.curriculum_units.0.grade_display.pass_status', 'passed');
});

it('keeps default weighted-percentage records rendering through existing fields', function () {
    [$student, $unit, $semester, $courseOffering] = gradeBreakdownFixture();

    AcademicRecord::factory()->create([
        'student_id' => $student->id,
        'unit_id' => $unit->id,
        'semester_id' => $semester->id,
        'course_offering_id' => $courseOffering->id,
        'final_percentage' => 72.5,
        'final_letter_grade' => 'B',
        'is_passed' => true,
        'grade_breakdown' => null,
    ]);

    Sanctum::actingAs($student);

    $this->getJson(route('v1.student.grades.index'))
        ->assertOk()
        ->assertJsonPath('data.grades_by_semester.0.curriculum_units.0.final_grade', 'B')
        ->assertJsonPath('data.grades_by_semester.0.curriculum_units.0.grade_display.scheme_engine', 'default_weighted_percentage')
        ->assertJsonPath('data.grades_by_semester.0.curriculum_units.0.grade_display.scale', 'percentage');
});

it('renders no grade_display for units the student is not enrolled in', function () {
    [$student] = gradeBreakdownFixture();

    Sanctum::actingAs($student);

    $this->getJson(route('v1.student.grades.index'))
        ->assertOk()
        ->assertJsonPath('data.grades_by_semester.0.curriculum_units.0.completion_status', 'not_enrolled')
        ->assertJsonPath('data.grades_by_semester.0.curriculum_units.0.grade_display', null);
});
