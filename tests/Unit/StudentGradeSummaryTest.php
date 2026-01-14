<?php

declare(strict_types=1);

use App\Models\AcademicRecord;
use App\Models\CurriculumUnit;
use App\Models\CurriculumVersion;
use App\Models\GpaCalculation;
use App\Models\Semester;
use App\Models\Student;
use App\Services\V1\Student\GradeService;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('uses stored gpa_calculation for semester summary when available', function () {
    $semester = Semester::factory()->spring()->create();

    // Create curriculum version bound to the same semester
    $curriculumVersion = CurriculumVersion::factory()
        ->withEffectiveSemester($semester)
        ->create();

    /** @var Student $student */
    $student = Student::factory()->create([
        'curriculum_version_id' => $curriculumVersion->id,
    ]);

    // Create curriculum unit and matching academic record
    $curriculumUnit = CurriculumUnit::factory()->create([
        'curriculum_version_id' => $curriculumVersion->id,
        'semester_number' => 1,
    ]);

    /** @var AcademicRecord $record */
    $record = AcademicRecord::factory()->create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'unit_id' => $curriculumUnit->unit_id,
    ]);

    // Stored GPA calculation for that semester
    GpaCalculation::create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'program_id' => null,
        'semester_gpa' => 3.21,
        'cumulative_gpa' => 3.21,
        'semester_quality_points' => 32.1,
        'cumulative_quality_points' => 32.1,
        'semester_credit_points' => 10,
        'cumulative_credit_points' => 10,
        'semester_credit_points_earned' => 10,
        'cumulative_credit_points_earned' => 10,
        'academic_standing' => 'normal',
        'is_finalized' => true,
        'finalized_at' => now(),
        'finalized_by_id' => null,
        'remarks' => null,
        'is_current' => true,
    ]);

    $service = app(GradeService::class);

    // Act: resolve grades (high-level call exercises buildGradesBySemester)
    actingAs($student->user);
    $result = $service->getStudentGrades($student);

    $firstSemester = $result['grades_by_semester'][0];
    expect($firstSemester['semester_summary']['semester_gpa'])->toBe(3.21)
        ->and($firstSemester['semester_summary']['total_credits'])->toBe(10.0)
        ->and($firstSemester['semester_summary']['earned_credits'])->toBe(10.0);
});
