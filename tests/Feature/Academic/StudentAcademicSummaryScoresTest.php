<?php

declare(strict_types=1);

use App\Models\AcademicRecord;
use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\GpaCalculation;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Unit;
use App\Services\StudentAcademicSummaryService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('derives score page attempted and earned credits from final academic records', function () {
    $campus = Campus::factory()->create();
    $sem1 = Semester::factory()->create([
        'code' => 'SUM2026-SCORES',
        'name' => 'Summer 2026 Scores',
        'start_date' => Carbon::create(2026, 5, 1),
        'end_date' => Carbon::create(2026, 8, 31),
    ]);
    $sem2 = Semester::factory()->create([
        'code' => 'FALL2026-SCORES',
        'name' => 'Fall 2026 Scores',
        'start_date' => Carbon::create(2026, 9, 1),
        'end_date' => Carbon::create(2026, 12, 31),
    ]);
    $student = Student::factory()->forCampus($campus)->create([
        'status' => 'intake_course',
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $sem1->id,
    ]);

    $makeRecord = function (Semester $semester, array $overrides = []) use ($campus, $student): AcademicRecord {
        $unit = $overrides['unit'] ?? Unit::factory()->create(['credit_points' => 3.0]);
        unset($overrides['unit']);

        $offering = CourseOffering::factory()->create([
            'semester_id' => $semester->id,
            'unit_id' => $unit->id,
            'campus_id' => $campus->id,
        ]);

        return AcademicRecord::factory()->create(array_merge([
            'student_id' => $student->id,
            'semester_id' => $semester->id,
            'unit_id' => $unit->id,
            'program_id' => $student->program_id,
            'campus_id' => $campus->id,
            'course_offering_id' => $offering->id,
            'credit_points' => (float) $unit->credit_points,
            'grade_status' => 'final',
            'excluded_from_gpa' => false,
            'is_passed' => true,
        ], $overrides));
    };

    $retakeUnit = Unit::factory()->create(['credit_points' => 3.0]);
    $makeRecord($sem1, [
        'unit' => $retakeUnit,
        'credit_points' => 3.0,
        'final_percentage' => 40.0,
        'attempt_number' => 1,
        'is_passed' => false,
    ]);
    $makeRecord($sem1, [
        'unit' => $retakeUnit,
        'credit_points' => 3.0,
        'final_percentage' => 70.0,
        'attempt_number' => 2,
        'is_repeat_course' => true,
        'is_passed' => true,
    ]);
    $makeRecord($sem2, [
        'credit_points' => 2.0,
        'final_percentage' => 80.0,
        'is_passed' => true,
    ]);
    $makeRecord($sem2, [
        'credit_points' => 4.0,
        'grade_status' => 'in_progress',
        'is_passed' => true,
    ]);
    $makeRecord($sem2, [
        'credit_points' => 5.0,
        'excluded_from_gpa' => true,
        'is_passed' => true,
    ]);

    GpaCalculation::create([
        'student_id' => $student->id,
        'semester_id' => $sem1->id,
        'program_id' => $student->program_id,
        'semester_gpa' => 55.0,
        'cumulative_gpa' => 55.0,
        'semester_credit_points' => 1.0,
        'cumulative_credit_points' => 1.0,
        'semester_credit_points_earned' => 1.0,
        'cumulative_credit_points_earned' => 1.0,
        'academic_standing' => 'normal',
        'is_finalized' => true,
        'is_current' => false,
    ]);
    GpaCalculation::create([
        'student_id' => $student->id,
        'semester_id' => $sem2->id,
        'program_id' => $student->program_id,
        'semester_gpa' => 80.0,
        'cumulative_gpa' => 61.25,
        'semester_credit_points' => 1.0,
        'cumulative_credit_points' => 2.0,
        'semester_credit_points_earned' => 1.0,
        'cumulative_credit_points_earned' => 2.0,
        'academic_standing' => 'normal',
        'is_finalized' => true,
        'is_current' => true,
    ]);

    $scores = app(StudentAcademicSummaryService::class)->getScoresData($student);
    $semesters = collect($scores['semesters'])->keyBy('semester_id');

    expect($semesters[$sem1->id]['credit_points_attempted'])->toBe(6.0)
        ->and($semesters[$sem1->id]['credit_points_earned'])->toBe(3.0)
        ->and($semesters[$sem2->id]['credit_points_attempted'])->toBe(2.0)
        ->and($semesters[$sem2->id]['credit_points_earned'])->toBe(2.0)
        ->and($scores['cumulative']['credit_points_attempted'])->toBe(8.0)
        ->and($scores['cumulative']['credit_points_earned'])->toBe(5.0);
});
