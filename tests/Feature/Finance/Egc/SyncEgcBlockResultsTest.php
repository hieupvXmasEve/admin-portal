<?php

declare(strict_types=1);

use App\Models\AcademicRecord;
use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\CurriculumVersion;
use App\Models\EgcBlock;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Unit;
use App\Modules\Finance\Actions\Egc\SyncEgcBlockResultsAction;
use App\Modules\Finance\Queries\Egc\ListEgcBlockResultsQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeSyncStudent(): Student
{
    $intakeSemester = Semester::factory()->create();
    $cv = CurriculumVersion::factory()->state(['semester_id' => $intakeSemester->id])->create();

    return Student::factory()->state([
        'curriculum_version_id' => $cv->id,
        'intake_semester_id' => $intakeSemester->id,
        'intake' => 1,
        'intake_mode' => 'sequential',
    ])->create();
}

function makeEgcUnit(int $level): Unit
{
    return Unit::factory()->state(['unit_type' => 'egc', 'level' => $level])->create();
}

function makeSyncCourseOffering(int $semesterId, int $unitId): CourseOffering
{
    $attributes = CourseOffering::factory()->state([
        'unit_id' => $unitId,
        'semester_id' => $semesterId,
    ])->raw();

    unset($attributes['drop_deadline'], $attributes['withdrawal_deadline']);

    return CourseOffering::query()->create($attributes);
}

function makeEgcAcademicRecord(Student $student, Semester $semester, Unit $unit, array $state = []): AcademicRecord
{
    $offering = makeSyncCourseOffering($semester->id, $unit->id);

    return AcademicRecord::factory()->state(array_merge([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'unit_id' => $unit->id,
        'course_offering_id' => $offering->id,
        'enrollment_date' => now()->toDateString(),
    ], $state))->create();
}

it('syncs result and attendance from academic records', function () {
    $semester = Semester::factory()->create();
    $student = makeSyncStudent();
    $unit = makeEgcUnit(1);

    $block = EgcBlock::factory()->state([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'level_number' => 1,
        'result' => EgcBlock::RESULT_PENDING,
    ])->create();

    makeEgcAcademicRecord($student, $semester, $unit, [
        'is_passed' => true,
        'override_pass' => false,
        'attendance_percentage' => 90.0,
    ]);

    SyncEgcBlockResultsAction::run($semester->id);

    $block->refresh();
    expect($block->result)->toBe(EgcBlock::RESULT_PASS);
    expect((float) $block->attendance_rate)->toBe(90.0);
    expect($block->synced_at)->not->toBeNull();
});

it('override_pass = true always resolves to pass', function () {
    $semester = Semester::factory()->create();
    $student = makeSyncStudent();
    $unit = makeEgcUnit(1);

    $block = EgcBlock::factory()->state([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'level_number' => 1,
        'result' => EgcBlock::RESULT_PENDING,
    ])->create();

    // Failed record, but override_pass = true
    makeEgcAcademicRecord($student, $semester, $unit, [
        'is_passed' => false,
        'override_pass' => true,
        'attendance_percentage' => 75.0,
    ]);

    SyncEgcBlockResultsAction::run($semester->id);

    expect($block->fresh()->result)->toBe(EgcBlock::RESULT_PASS);
});

it('uses latest record when duplicates exist', function () {
    $semester = Semester::factory()->create();
    $student = makeSyncStudent();
    $unit = makeEgcUnit(1);

    $block = EgcBlock::factory()->state([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'level_number' => 1,
        'result' => EgcBlock::RESULT_PENDING,
    ])->create();

    // Older record: failed
    makeEgcAcademicRecord($student, $semester, $unit, [
        'is_passed' => false,
        'override_pass' => false,
        'attendance_percentage' => 60.0,
    ]);

    // Newer record: passed (higher id)
    makeEgcAcademicRecord($student, $semester, $unit, [
        'is_passed' => true,
        'override_pass' => false,
        'attendance_percentage' => 85.0,
    ]);

    SyncEgcBlockResultsAction::run($semester->id);

    // Latest (highest id) should win
    expect($block->fresh()->result)->toBe(EgcBlock::RESULT_PASS);
});

it('re-sync overwrites previous result', function () {
    $semester = Semester::factory()->create();
    $student = makeSyncStudent();
    $unit = makeEgcUnit(1);

    $block = EgcBlock::factory()->state([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'level_number' => 1,
        'result' => EgcBlock::RESULT_PASS, // previously set to pass
        'synced_at' => now()->subDay(),
    ])->create();

    // Now academic record says fail
    makeEgcAcademicRecord($student, $semester, $unit, [
        'is_passed' => false,
        'override_pass' => false,
        'attendance_percentage' => 70.0,
    ]);

    SyncEgcBlockResultsAction::run($semester->id);

    expect($block->fresh()->result)->toBe(EgcBlock::RESULT_FAIL);
});

it('does not overwrite a block to fail when the immediate next block has progressed to a higher level', function () {
    $semester = Semester::factory()->state([
        'start_date' => '2026-01-05',
        'end_date' => '2026-04-30',
    ])->create();
    $nextSemester = Semester::factory()->state([
        'start_date' => '2026-05-04',
        'end_date' => '2026-08-31',
    ])->create();
    $student = makeSyncStudent();

    $failedLevel = makeEgcUnit(4);

    $block = EgcBlock::factory()->state([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'block_number' => 2,
        'level_number' => 4,
        'result' => EgcBlock::RESULT_PASS,
        'attendance_rate' => 82.86,
    ])->create();

    EgcBlock::factory()->state([
        'student_id' => $student->id,
        'semester_id' => $nextSemester->id,
        'block_number' => 1,
        'level_number' => 5,
        'result' => EgcBlock::RESULT_PENDING,
    ])->create();

    makeEgcAcademicRecord($student, $semester, $failedLevel, [
        'completion_status' => 'completed',
        'is_passed' => false,
        'override_pass' => false,
        'attendance_percentage' => 82.86,
    ]);

    $summary = SyncEgcBlockResultsAction::run($semester->id);

    $block->refresh();

    expect($summary['synced'])->toBe(0)
        ->and($summary['skipped'])->toHaveCount(1)
        ->and($summary['skipped'][0]['reason'])->toBe('failed_result_conflicts_with_later_egc_progression')
        ->and($block->result)->toBe(EgcBlock::RESULT_PASS)
        ->and($block->synced_at)->toBeNull();
});

it('keeps a fail result when the immediate next block is the same-level retake', function () {
    $semester = Semester::factory()->state([
        'start_date' => '2026-01-05',
        'end_date' => '2026-04-30',
    ])->create();
    $nextSemester = Semester::factory()->state([
        'start_date' => '2026-05-04',
        'end_date' => '2026-08-31',
    ])->create();
    $student = makeSyncStudent();
    $student->update(['gc_current_level' => 4]);

    $failedLevel = makeEgcUnit(3);

    $sourceBlock = EgcBlock::factory()->state([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'block_number' => 2,
        'level_number' => 3,
        'result' => EgcBlock::RESULT_PASS,
        'attendance_rate' => 91.43,
    ])->create();

    EgcBlock::factory()->state([
        'student_id' => $student->id,
        'semester_id' => $nextSemester->id,
        'block_number' => 1,
        'level_number' => 3,
        'result' => EgcBlock::RESULT_PENDING,
        'is_retake' => true,
    ])->create();

    EgcBlock::factory()->state([
        'student_id' => $student->id,
        'semester_id' => $nextSemester->id,
        'block_number' => 2,
        'level_number' => 4,
        'result' => EgcBlock::RESULT_PENDING,
    ])->create();

    makeEgcAcademicRecord($student, $semester, $failedLevel, [
        'completion_status' => 'completed',
        'is_passed' => false,
        'override_pass' => false,
        'attendance_percentage' => 91.43,
    ]);

    $summary = SyncEgcBlockResultsAction::run($semester->id);

    $sourceBlock->refresh();

    expect($summary['synced'])->toBe(1)
        ->and($summary['skipped'])->toBeEmpty()
        ->and($sourceBlock->result)->toBe(EgcBlock::RESULT_FAIL)
        ->and((float) $sourceBlock->attendance_rate)->toBe(91.43)
        ->and($sourceBlock->synced_at)->not->toBeNull();
});

it('matches duplicate same-level blocks by registration order and keeps in-progress attempts pending', function () {
    $semester = Semester::factory()->create();
    $student = makeSyncStudent();
    $unit = makeEgcUnit(2);

    $firstOffering = makeSyncCourseOffering($semester->id, $unit->id);
    $secondOffering = makeSyncCourseOffering($semester->id, $unit->id);

    CourseRegistration::create([
        'student_id' => $student->id,
        'course_offering_id' => $firstOffering->id,
        'semester_id' => $semester->id,
        'registration_status' => 'confirmed',
        'registration_date' => now(),
        'registration_method' => 'admin_override',
        'credit_hours' => 3,
        'credit_points' => 3,
        'attempt_number' => 1,
    ]);

    CourseRegistration::create([
        'student_id' => $student->id,
        'course_offering_id' => $secondOffering->id,
        'semester_id' => $semester->id,
        'registration_status' => 'confirmed',
        'registration_date' => now()->addMinute(),
        'registration_method' => 'admin_override',
        'credit_hours' => 3,
        'credit_points' => 3,
        'attempt_number' => 2,
        'is_retake' => true,
    ]);

    $blockOne = EgcBlock::factory()->state([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'block_number' => 1,
        'level_number' => 2,
        'result' => EgcBlock::RESULT_PENDING,
    ])->create();

    $blockTwo = EgcBlock::factory()->state([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'block_number' => 2,
        'level_number' => 2,
        'result' => EgcBlock::RESULT_FAIL,
        'attendance_rate' => 88.57,
    ])->create();

    AcademicRecord::factory()->state([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'unit_id' => $unit->id,
        'course_offering_id' => $firstOffering->id,
        'enrollment_date' => now()->toDateString(),
        'completion_status' => 'completed',
        'is_passed' => false,
        'override_pass' => false,
        'attendance_percentage' => 88.57,
    ])->create();

    AcademicRecord::factory()->state([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'unit_id' => $unit->id,
        'course_offering_id' => $secondOffering->id,
        'enrollment_date' => now()->toDateString(),
        'completion_status' => 'in_progress',
        'is_passed' => null,
        'override_pass' => false,
        'attendance_percentage' => 0,
    ])->create();

    SyncEgcBlockResultsAction::run($semester->id);

    expect($blockOne->fresh()->result)->toBe(EgcBlock::RESULT_FAIL);
    expect((float) $blockOne->fresh()->attendance_rate)->toBe(88.57);
    expect($blockTwo->fresh()->result)->toBe(EgcBlock::RESULT_PENDING);
    expect($blockTwo->fresh()->attendance_rate)->toBeNull();
});

it('filters block results by campus', function () {
    $semester = Semester::factory()->create();
    $campusA = Campus::factory()->create();
    $campusB = Campus::factory()->create();

    $visibleStudent = makeSyncStudent();
    $visibleStudent->update(['campus_id' => $campusA->id]);

    $hiddenStudent = makeSyncStudent();
    $hiddenStudent->update(['campus_id' => $campusB->id]);

    EgcBlock::factory()->state([
        'student_id' => $visibleStudent->id,
        'semester_id' => $semester->id,
        'block_number' => 1,
        'level_number' => 2,
        'result' => EgcBlock::RESULT_PENDING,
    ])->create();

    EgcBlock::factory()->state([
        'student_id' => $hiddenStudent->id,
        'semester_id' => $semester->id,
        'block_number' => 1,
        'level_number' => 2,
        'result' => EgcBlock::RESULT_PENDING,
    ])->create();

    $results = app(ListEgcBlockResultsQuery::class)->handle($semester->id, ['per_page' => 50], $campusA->id);

    expect(collect($results->items())->pluck('student.id')->all())->toBe([$visibleStudent->id]);
});
