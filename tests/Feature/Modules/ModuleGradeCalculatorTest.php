<?php

declare(strict_types=1);

use App\Models\AcademicRecord;
use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\Module;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Unit;
use App\Services\ModuleGradeCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @param  array{grading_type?: string, weight?: float|null, order?: int, credit_points?: float, status?: string, passed?: bool, fg?: float|null}  $opts
 */
function calcUnitRecord(Module $module, Student $student, Campus $campus, array $opts = []): void
{
    $unit = Unit::factory()->create(['credit_points' => $opts['credit_points'] ?? 3.0]);

    $module->units()->attach($unit->id, [
        'grading_type' => $opts['grading_type'] ?? 'grade',
        'weight' => $opts['weight'] ?? null,
        'order' => $opts['order'] ?? 1,
    ]);

    $offering = CourseOffering::factory()->create(['unit_id' => $unit->id, 'campus_id' => $campus->id]);
    $passed = $opts['passed'] ?? true;
    $fg = $opts['fg'] ?? 4.0;

    AcademicRecord::factory()->create([
        'student_id' => $student->id,
        'unit_id' => $unit->id,
        'campus_id' => $campus->id,
        'program_id' => $student->program_id,
        'course_offering_id' => $offering->id,
        'semester_id' => $offering->semester_id,
        'completion_status' => $opts['status'] ?? 'completed',
        'is_passed' => $passed,
        'grade_points' => $passed ? 3.5 : 0.0,
        'override_pass' => false,
        'final_letter_grade' => $fg === null ? 'P' : (string) (int) $fg,
        'grade_breakdown' => $fg === null ? [] : ['grade_points' => $fg],
    ]);
}

function calcStudent(Campus $campus): Student
{
    $semester = Semester::factory()->create();

    return Student::factory()->forCampus($campus)->create([
        'status' => 'intake_course',
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $semester->id,
    ]);
}

function calcGrade(Module $module, Student $student): ?float
{
    $module->load('units');
    $records = $student->academicRecords()->whereIn('unit_id', $module->units->pluck('id'))->get();

    return (new ModuleGradeCalculator)->calculate($records, $module);
}

it('credit-weights the 0-5 grade and rounds to an integer', function () {
    $campus = Campus::factory()->create();
    $student = calcStudent($campus);
    $module = Module::create(['campus_id' => $campus->id, 'code' => 'M1', 'name' => 'M', 'grading_type' => 'grade', 'total_credits' => 0]);

    calcUnitRecord($module, $student, $campus, ['credit_points' => 1.0, 'fg' => 1.0, 'order' => 1]);
    calcUnitRecord($module, $student, $campus, ['credit_points' => 9.0, 'fg' => 5.0, 'order' => 2]);

    // (1*1 + 5*9) / 10 = 4.6 -> 5
    expect(calcGrade($module, $student))->toBe(5.0);
});

it('honours an explicit pivot weight over credit points', function () {
    $campus = Campus::factory()->create();
    $student = calcStudent($campus);
    $module = Module::create(['campus_id' => $campus->id, 'code' => 'M2', 'name' => 'M', 'grading_type' => 'grade', 'total_credits' => 0]);

    calcUnitRecord($module, $student, $campus, ['credit_points' => 9.0, 'weight' => 1.0, 'fg' => 1.0, 'order' => 1]);
    calcUnitRecord($module, $student, $campus, ['credit_points' => 1.0, 'weight' => 1.0, 'fg' => 3.0, 'order' => 2]);

    // Equal pivot weights ignore the lopsided credits: (1 + 3) / 2 = 2
    expect(calcGrade($module, $student))->toBe(2.0);
});

it('withholds the grade when a graded unit has failed', function () {
    $campus = Campus::factory()->create();
    $student = calcStudent($campus);
    $module = Module::create(['campus_id' => $campus->id, 'code' => 'M3', 'name' => 'M', 'grading_type' => 'grade', 'total_credits' => 0]);

    calcUnitRecord($module, $student, $campus, ['fg' => 4.0, 'order' => 1]);
    calcUnitRecord($module, $student, $campus, ['status' => 'failed', 'passed' => false, 'fg' => 0.0, 'order' => 2]);

    expect(calcGrade($module, $student))->toBeNull();
});

it('withholds the grade while a graded unit is in progress', function () {
    $campus = Campus::factory()->create();
    $student = calcStudent($campus);
    $module = Module::create(['campus_id' => $campus->id, 'code' => 'M4', 'name' => 'M', 'grading_type' => 'grade', 'total_credits' => 0]);

    calcUnitRecord($module, $student, $campus, ['fg' => 4.0, 'order' => 1]);
    calcUnitRecord($module, $student, $campus, ['status' => 'in_progress', 'passed' => false, 'fg' => null, 'order' => 2]);

    expect(calcGrade($module, $student))->toBeNull();
});

it('returns null for a module of only pass/fail units', function () {
    $campus = Campus::factory()->create();
    $student = calcStudent($campus);
    $module = Module::create(['campus_id' => $campus->id, 'code' => 'M5', 'name' => 'M', 'grading_type' => 'grade', 'total_credits' => 0]);

    calcUnitRecord($module, $student, $campus, ['grading_type' => 'pass_fail', 'fg' => null, 'order' => 1]);
    calcUnitRecord($module, $student, $campus, ['grading_type' => 'pass_fail', 'fg' => null, 'order' => 2]);

    expect(calcGrade($module, $student))->toBeNull();
});

it('partitions graded vs pass/fail by the module_units pivot, not the course offering', function () {
    $campus = Campus::factory()->create();
    $student = calcStudent($campus);
    $module = Module::create(['campus_id' => $campus->id, 'code' => 'M6', 'name' => 'M', 'grading_type' => 'grade', 'total_credits' => 0]);

    // Pivot marks this graded; its grade must count even though the course offering may differ.
    calcUnitRecord($module, $student, $campus, ['grading_type' => 'grade', 'fg' => 5.0, 'order' => 1]);
    // Pivot marks this pass/fail; excluded from the number.
    calcUnitRecord($module, $student, $campus, ['grading_type' => 'pass_fail', 'fg' => null, 'order' => 2]);

    expect(calcGrade($module, $student))->toBe(5.0);
});
