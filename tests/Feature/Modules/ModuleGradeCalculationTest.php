<?php

declare(strict_types=1);

use App\Models\AcademicRecord;
use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\Module;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Unit;
use App\Services\ModuleProgressService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Build a module on the given campus.
 */
function makeModule(Campus $campus, string $code = 'SW1'): Module
{
    return Module::create([
        'campus_id' => $campus->id,
        'code' => $code,
        'name' => 'Software 1',
        'grading_type' => 'grade',
        'total_credits' => 0,
    ]);
}

/**
 * Attach a unit to a module and create the student's academic record for it.
 *
 * @param  array{grading_type?: string, weight?: float|null, order?: int, credit_points?: float, status?: string, passed?: bool, fg?: float|null}  $opts
 */
function attachUnitWithRecord(Module $module, Student $student, Campus $campus, array $opts = []): Unit
{
    $unit = Unit::factory()->create([
        'credit_points' => $opts['credit_points'] ?? 3.0,
    ]);

    $module->units()->attach($unit->id, [
        'grading_type' => $opts['grading_type'] ?? 'grade',
        'weight' => $opts['weight'] ?? null,
        'order' => $opts['order'] ?? 1,
    ]);

    $passed = $opts['passed'] ?? true;
    $status = $opts['status'] ?? 'completed';
    $fg = $opts['fg'] ?? 4.0;

    $offering = CourseOffering::factory()->create([
        'unit_id' => $unit->id,
        'campus_id' => $campus->id,
    ]);

    AcademicRecord::factory()->create([
        'student_id' => $student->id,
        'unit_id' => $unit->id,
        'campus_id' => $campus->id,
        'program_id' => $student->program_id,
        'course_offering_id' => $offering->id,
        'semester_id' => $offering->semester_id,
        'completion_status' => $status,
        // Top-level column only drives isPassed(); deliberately different from the
        // breakdown value so the test proves the module reads grade_breakdown.
        'grade_points' => $passed ? 3.5 : 0.0,
        'override_pass' => false,
        'final_letter_grade' => $fg === null ? 'P' : (string) (int) $fg,
        'grade_breakdown' => $fg === null ? [] : ['grade_points' => $fg],
    ]);

    return $unit;
}

function makeStudent(Campus $campus): Student
{
    $semester = Semester::factory()->create();

    return Student::factory()->forCampus($campus)->create([
        'status' => 'intake_course',
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $semester->id,
    ]);
}

function progressFor(Module $module, Student $student): array
{
    return app(ModuleProgressService::class)->calculateModuleProgress($module, $student);
}

it('averages graded units on the 0-5 grade and rounds to an integer', function () {
    $campus = Campus::factory()->create();
    $student = makeStudent($campus);
    $module = makeModule($campus);

    attachUnitWithRecord($module, $student, $campus, ['credit_points' => 3.0, 'fg' => 3.0, 'order' => 1]);
    attachUnitWithRecord($module, $student, $campus, ['credit_points' => 3.0, 'fg' => 5.0, 'order' => 2]);

    // Equal credit: (3 + 5) / 2 = 4
    expect(progressFor($module, $student)['grade'])->toBe(4.0);
});

it('weights the average by unit credit points when pivot weight is null', function () {
    $campus = Campus::factory()->create();
    $student = makeStudent($campus);
    $module = makeModule($campus);

    attachUnitWithRecord($module, $student, $campus, ['credit_points' => 1.0, 'fg' => 1.0, 'order' => 1]);
    attachUnitWithRecord($module, $student, $campus, ['credit_points' => 9.0, 'fg' => 5.0, 'order' => 2]);

    // Credit-weighted: (1*1 + 5*9) / 10 = 4.6 -> 5  (plain mean would be 3)
    expect(progressFor($module, $student)['grade'])->toBe(5.0);
});

it('withholds the module grade when a graded unit has failed', function () {
    $campus = Campus::factory()->create();
    $student = makeStudent($campus);
    $module = makeModule($campus);

    attachUnitWithRecord($module, $student, $campus, ['fg' => 4.0, 'order' => 1]);
    attachUnitWithRecord($module, $student, $campus, ['status' => 'failed', 'passed' => false, 'fg' => 0.0, 'order' => 2]);

    $progress = progressFor($module, $student);
    expect($progress['grade'])->toBeNull();
    expect($progress['status'])->toBe('failed');
});

it('withholds the module grade while a graded unit is still in progress', function () {
    $campus = Campus::factory()->create();
    $student = makeStudent($campus);
    $module = makeModule($campus);

    attachUnitWithRecord($module, $student, $campus, ['fg' => 4.0, 'order' => 1]);
    attachUnitWithRecord($module, $student, $campus, ['status' => 'in_progress', 'passed' => false, 'fg' => null, 'order' => 2]);

    $progress = progressFor($module, $student);
    expect($progress['grade'])->toBeNull();
    expect($progress['status'])->toBe('in_progress');
});

it('returns a null grade for a module made of only pass/fail units', function () {
    $campus = Campus::factory()->create();
    $student = makeStudent($campus);
    $module = makeModule($campus);

    attachUnitWithRecord($module, $student, $campus, ['grading_type' => 'pass_fail', 'fg' => null, 'order' => 1]);
    attachUnitWithRecord($module, $student, $campus, ['grading_type' => 'pass_fail', 'fg' => null, 'order' => 2]);

    $progress = progressFor($module, $student);
    expect($progress['grade'])->toBeNull();
    expect($progress['status'])->toBe('passed');
});

it('excludes pass/fail units from the number but lets a failed one block status', function () {
    $campus = Campus::factory()->create();
    $student = makeStudent($campus);
    $module = makeModule($campus);

    attachUnitWithRecord($module, $student, $campus, ['grading_type' => 'grade', 'fg' => 5.0, 'order' => 1]);
    attachUnitWithRecord($module, $student, $campus, ['grading_type' => 'pass_fail', 'status' => 'failed', 'passed' => false, 'fg' => null, 'order' => 2]);

    $progress = progressFor($module, $student);
    // Graded unit average is computed from graded units only...
    expect($progress['grade'])->toBe(5.0);
    // ...but the failed pass/fail unit still fails the module.
    expect($progress['status'])->toBe('failed');
});
