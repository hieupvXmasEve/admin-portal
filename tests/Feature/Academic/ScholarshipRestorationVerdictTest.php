<?php

declare(strict_types=1);

use App\Models\AcademicRecord;
use App\Models\AssessmentComponentDetail;
use App\Models\AssessmentComponentDetailScore;
use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Unit;
use App\Shared\Contracts\Academic\ScholarshipRestorationVerdictReader;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{campus: Campus, semester: Semester, student: Student}
 */
function verdictBaseContext(): array
{
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->create(['start_date' => now(), 'is_archived' => false]);
    $student = Student::factory()->create([
        'campus_id' => $campus->id,
        'status' => 'intake_course',
        'intake' => 1,
        'intake_semester_id' => $semester->id,
    ]);

    return compact('campus', 'semester', 'student');
}

function verdictRecord(array $ctx, array $overrides = []): AcademicRecord
{
    $unit = Unit::factory()->create(['unit_type' => 'general']);
    $offering = CourseOffering::factory()->create([
        'semester_id' => $ctx['semester']->id,
        'unit_id' => $unit->id,
        'campus_id' => $ctx['campus']->id,
    ]);

    return AcademicRecord::factory()->create(array_merge([
        'student_id' => $ctx['student']->id,
        'course_offering_id' => $offering->id,
        'semester_id' => $ctx['semester']->id,
        'unit_id' => $unit->id,
        'campus_id' => $ctx['campus']->id,
        'is_passed' => true,
        'override_pass' => false,
        'grade_finalized_date' => now()->subWeek(),
    ], $overrides));
}

it('returns NOT_FINALIZED when the student has no qualifying record in the semester', function () {
    $ctx = verdictBaseContext();

    $verdict = app(ScholarshipRestorationVerdictReader::class)
        ->verdict($ctx['student']->id, $ctx['semester']->id);

    expect($verdict)->toBe(ScholarshipRestorationVerdictReader::NOT_FINALIZED);
});

it('returns CLEAN when all units passed and are finalized', function () {
    $ctx = verdictBaseContext();
    verdictRecord($ctx, ['is_passed' => true]);
    verdictRecord($ctx, ['is_passed' => true]);

    $verdict = app(ScholarshipRestorationVerdictReader::class)
        ->verdict($ctx['student']->id, $ctx['semester']->id);

    expect($verdict)->toBe(ScholarshipRestorationVerdictReader::CLEAN);
});

it('returns STILL_FAILING when a qualifying failed record exists', function () {
    $ctx = verdictBaseContext();
    verdictRecord($ctx, ['is_passed' => true]);
    verdictRecord($ctx, ['is_passed' => false]);

    $verdict = app(ScholarshipRestorationVerdictReader::class)
        ->verdict($ctx['student']->id, $ctx['semester']->id);

    expect($verdict)->toBe(ScholarshipRestorationVerdictReader::STILL_FAILING);
});

it('treats an overridden failure as not failing (CLEAN, not STILL_FAILING)', function () {
    $ctx = verdictBaseContext();
    verdictRecord($ctx, ['is_passed' => false, 'override_pass' => true]);

    $verdict = app(ScholarshipRestorationVerdictReader::class)
        ->verdict($ctx['student']->id, $ctx['semester']->id);

    expect($verdict)->toBe(ScholarshipRestorationVerdictReader::CLEAN);
});

it('returns NOT_FINALIZED when a unit lacks grade_finalized_date', function () {
    $ctx = verdictBaseContext();
    verdictRecord($ctx, ['is_passed' => true]);
    verdictRecord($ctx, ['is_passed' => true, 'grade_finalized_date' => null]);

    $verdict = app(ScholarshipRestorationVerdictReader::class)
        ->verdict($ctx['student']->id, $ctx['semester']->id);

    expect($verdict)->toBe(ScholarshipRestorationVerdictReader::NOT_FINALIZED);
});

it('returns NOT_FINALIZED when is_passed is NULL (not yet evaluated)', function () {
    $ctx = verdictBaseContext();
    verdictRecord($ctx, ['is_passed' => null]);

    $verdict = app(ScholarshipRestorationVerdictReader::class)
        ->verdict($ctx['student']->id, $ctx['semester']->id);

    expect($verdict)->toBe(ScholarshipRestorationVerdictReader::NOT_FINALIZED);
});

it('returns NOT_FINALIZED when a component-level appeal is pending', function () {
    $ctx = verdictBaseContext();
    $record = verdictRecord($ctx, ['is_passed' => true]);

    $detail = AssessmentComponentDetail::factory()->create();

    AssessmentComponentDetailScore::create([
        'assessment_component_detail_id' => $detail->id,
        'student_id' => $ctx['student']->id,
        'course_offering_id' => $record->course_offering_id,
        'appeal_status' => 'pending',
        'appeal_requested' => true,
    ]);

    $verdict = app(ScholarshipRestorationVerdictReader::class)
        ->verdict($ctx['student']->id, $ctx['semester']->id);

    expect($verdict)->toBe(ScholarshipRestorationVerdictReader::NOT_FINALIZED);
});

it('ignores an EGC unit failure when computing the verdict', function () {
    $ctx = verdictBaseContext();
    verdictRecord($ctx, ['is_passed' => true]);

    $egcUnit = Unit::factory()->create(['unit_type' => 'egc']);
    $egcOffering = CourseOffering::factory()->create([
        'semester_id' => $ctx['semester']->id,
        'unit_id' => $egcUnit->id,
        'campus_id' => $ctx['campus']->id,
    ]);

    AcademicRecord::factory()->create([
        'student_id' => $ctx['student']->id,
        'course_offering_id' => $egcOffering->id,
        'semester_id' => $ctx['semester']->id,
        'unit_id' => $egcUnit->id,
        'campus_id' => $ctx['campus']->id,
        'is_passed' => false,
        'override_pass' => false,
        'grade_finalized_date' => now()->subWeek(),
    ]);

    $verdict = app(ScholarshipRestorationVerdictReader::class)
        ->verdict($ctx['student']->id, $ctx['semester']->id);

    expect($verdict)->toBe(ScholarshipRestorationVerdictReader::CLEAN);
});
