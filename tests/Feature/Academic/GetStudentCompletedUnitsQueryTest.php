<?php

declare(strict_types=1);

use App\Models\AcademicRecord;
use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\CurriculumUnit;
use App\Models\CurriculumVersion;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Unit;
use App\Modules\Academic\Progression\Queries\Reporting\GetStudentCompletedUnitsQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function makeCampusStudent(array $studentAttrs = []): Student
{
    $campus = Campus::factory()->create();

    return Student::factory()->create(array_merge(['campus_id' => $campus->id, 'intake' => 1, 'intake_semester_id' => Semester::factory()], $studentAttrs));
}

it('excludes is_passed = 0 from gc, major, count and credits', function () {
    $student = makeCampusStudent();
    $unit = Unit::factory()->create(['unit_type' => 'egc']);
    AcademicRecord::factory()->create([
        'course_offering_id' => CourseOffering::factory(),
        'student_id' => $student->id,
        'unit_id' => $unit->id,
        'is_passed' => false,
        'credit_points_earned' => 10,
    ]);

    $result = app(GetStudentCompletedUnitsQuery::class)->handle(['campus_id' => $student->campus_id]);
    $row = collect($result['data'])->firstWhere('id', $student->id);

    expect($row['gc'])->toBeEmpty()
        ->and($row['units_count'])->toBe(0)
        ->and((float) $row['credits_earned'])->toBe(0.0);
});

it('excludes is_passed IS NULL from gc, major, count and credits', function () {
    $student = makeCampusStudent();
    $unit = Unit::factory()->create(['unit_type' => 'egc']);
    AcademicRecord::factory()->create([
        'course_offering_id' => CourseOffering::factory(),
        'student_id' => $student->id,
        'unit_id' => $unit->id,
        'is_passed' => null,
        'credit_points_earned' => 10,
    ]);

    $result = app(GetStudentCompletedUnitsQuery::class)->handle(['campus_id' => $student->campus_id]);
    $row = collect($result['data'])->firstWhere('id', $student->id);

    expect($row['gc'])->toBeEmpty()->and($row['units_count'])->toBe(0);
});

it('dedupes two passed records for the same unit into one chip, credits counted once', function () {
    $student = makeCampusStudent();
    $unit = Unit::factory()->create(['unit_type' => 'general', 'code' => 'SE001']);
    AcademicRecord::factory()->create([
        'course_offering_id' => CourseOffering::factory(),
        'student_id' => $student->id,
        'unit_id' => $unit->id,
        'is_passed' => true,
        'attempt_number' => 1,
        'credit_points_earned' => 10,
    ]);
    AcademicRecord::factory()->create([
        'course_offering_id' => CourseOffering::factory(),
        'student_id' => $student->id,
        'unit_id' => $unit->id,
        'is_passed' => true,
        'attempt_number' => 2,
        'credit_points_earned' => 10,
    ]);

    $result = app(GetStudentCompletedUnitsQuery::class)->handle(['campus_id' => $student->campus_id]);
    $row = collect($result['data'])->firstWhere('id', $student->id);

    expect($row['major'])->toHaveCount(1)
        ->and($row['units_count'])->toBe(1)
        ->and((float) $row['credits_earned'])->toBe(10.0);
});

it('splits units into gc and major by unit_type', function () {
    $student = makeCampusStudent();
    $gcUnit = Unit::factory()->create(['unit_type' => 'egc', 'code' => 'EGCF']);
    $majorUnit = Unit::factory()->create(['unit_type' => 'general', 'code' => 'SE001']);
    AcademicRecord::factory()->create(['course_offering_id' => CourseOffering::factory(), 'student_id' => $student->id, 'unit_id' => $gcUnit->id, 'is_passed' => true]);
    AcademicRecord::factory()->create(['course_offering_id' => CourseOffering::factory(), 'student_id' => $student->id, 'unit_id' => $majorUnit->id, 'is_passed' => true]);

    $result = app(GetStudentCompletedUnitsQuery::class)->handle(['campus_id' => $student->campus_id]);
    $row = collect($result['data'])->firstWhere('id', $student->id);

    expect($row['gc'])->toHaveCount(1)->and($row['gc'][0]['code'])->toBe('EGCF')
        ->and($row['major'])->toHaveCount(1)->and($row['major'][0]['code'])->toBe('SE001');
});

it('shows a row with empty arrays and zero counts for a student with no passed unit', function () {
    $student = makeCampusStudent();

    $result = app(GetStudentCompletedUnitsQuery::class)->handle(['campus_id' => $student->campus_id]);
    $row = collect($result['data'])->firstWhere('id', $student->id);

    expect($row)->not->toBeNull()
        ->and($row['gc'])->toBeEmpty()
        ->and($row['major'])->toBeEmpty()
        ->and($row['units_count'])->toBe(0)
        ->and((float) $row['credits_earned'])->toBe(0.0);
});

it('excludes students from a different campus', function () {
    $inCampus = makeCampusStudent();
    $otherCampusStudent = Student::factory()->create(['campus_id' => Campus::factory()->create()->id, 'intake' => 1, 'intake_semester_id' => Semester::factory()]);

    $result = app(GetStudentCompletedUnitsQuery::class)->handle(['campus_id' => $inCampus->campus_id]);
    $ids = collect($result['data'])->pluck('id');

    expect($ids)->toContain($inCampus->id)->not->toContain($otherCampusStudent->id);
});

it('filters by program_id and keyword matching student_id or full_name', function () {
    $campus = Campus::factory()->create();
    $target = Student::factory()->create([
        'campus_id' => $campus->id,
        'student_id' => 'SW21999',
        'full_name' => 'Findable Person',
        'intake' => 1, 'intake_semester_id' => Semester::factory(),
    ]);
    $other = Student::factory()->create(['campus_id' => $campus->id, 'intake' => 1, 'intake_semester_id' => Semester::factory()]);

    $byKeywordId = app(GetStudentCompletedUnitsQuery::class)->handle(['campus_id' => $campus->id, 'keyword' => 'SW21999']);
    expect(collect($byKeywordId['data'])->pluck('id'))->toContain($target->id)->not->toContain($other->id);

    $byKeywordName = app(GetStudentCompletedUnitsQuery::class)->handle(['campus_id' => $campus->id, 'keyword' => 'Findable']);
    expect(collect($byKeywordName['data'])->pluck('id'))->toContain($target->id);

    $byProgram = app(GetStudentCompletedUnitsQuery::class)->handle(['campus_id' => $campus->id, 'program_id' => $target->program_id]);
    expect(collect($byProgram['data'])->pluck('id'))->toContain($target->id);
});

it('sorts by credits_earned desc', function () {
    $campus = Campus::factory()->create();
    $low = Student::factory()->create(['campus_id' => $campus->id, 'intake' => 1, 'intake_semester_id' => Semester::factory()]);
    $high = Student::factory()->create(['campus_id' => $campus->id, 'intake' => 1, 'intake_semester_id' => Semester::factory()]);
    $unit = Unit::factory()->create(['unit_type' => 'general']);
    AcademicRecord::factory()->create(['course_offering_id' => CourseOffering::factory(), 'student_id' => $low->id, 'unit_id' => $unit->id, 'is_passed' => true, 'credit_points_earned' => 5]);
    $unit2 = Unit::factory()->create(['unit_type' => 'general']);
    AcademicRecord::factory()->create(['course_offering_id' => CourseOffering::factory(), 'student_id' => $high->id, 'unit_id' => $unit2->id, 'is_passed' => true, 'credit_points_earned' => 20]);

    $result = app(GetStudentCompletedUnitsQuery::class)->handle([
        'campus_id' => $campus->id,
        'sort' => 'credits_earned',
        'direction' => 'desc',
    ]);

    $ids = collect($result['data'])->pluck('id')->values();
    expect($ids->search($high->id))->toBeLessThan($ids->search($low->id));
});

it('sorts by credits_earned desc with a retake, matching the displayed dedupe', function () {
    $campus = Campus::factory()->create();
    $retaker = Student::factory()->create(['campus_id' => $campus->id, 'intake' => 1, 'intake_semester_id' => Semester::factory()]);
    $single = Student::factory()->create(['campus_id' => $campus->id, 'intake' => 1, 'intake_semester_id' => Semester::factory()]);

    // Retake: two pass records on the same unit — the SQL dedupe used for
    // sorting and the PHP dedupe used for the displayed payload must agree
    // on which attempt counts, or a retake could sort into the wrong slot.
    $retakeUnit = Unit::factory()->create(['unit_type' => 'general']);
    AcademicRecord::factory()->create(['course_offering_id' => CourseOffering::factory(), 'student_id' => $retaker->id, 'unit_id' => $retakeUnit->id, 'is_passed' => true, 'attempt_number' => 1, 'credit_points_earned' => 6]);
    AcademicRecord::factory()->create(['course_offering_id' => CourseOffering::factory(), 'student_id' => $retaker->id, 'unit_id' => $retakeUnit->id, 'is_passed' => true, 'attempt_number' => 2, 'credit_points_earned' => 20]);

    $singleUnit = Unit::factory()->create(['unit_type' => 'general']);
    AcademicRecord::factory()->create(['course_offering_id' => CourseOffering::factory(), 'student_id' => $single->id, 'unit_id' => $singleUnit->id, 'is_passed' => true, 'credit_points_earned' => 10]);

    $result = app(GetStudentCompletedUnitsQuery::class)->handle([
        'campus_id' => $campus->id,
        'sort' => 'credits_earned',
        'direction' => 'desc',
    ]);

    $ids = collect($result['data'])->pluck('id')->values();
    $retakerRow = collect($result['data'])->firstWhere('id', $retaker->id);

    expect((float) $retakerRow['credits_earned'])->toBe(20.0)
        ->and($ids->search($retaker->id))->toBeLessThan($ids->search($single->id));
});

it('does not leak another campus when campus_id is overridden by the caller', function () {
    $sessionCampus = Campus::factory()->create();
    $otherCampus = Campus::factory()->create();
    $otherStudent = Student::factory()->create(['campus_id' => $otherCampus->id, 'intake' => 1, 'intake_semester_id' => Semester::factory()]);

    session(['current_campus_id' => $sessionCampus->id]);

    // Simulate the controller's own filters — campus_id always forced from
    // the session — so a caller passing a foreign campus_id can't leak rows.
    $result = app(GetStudentCompletedUnitsQuery::class)->handle(['campus_id' => session('current_campus_id')]);

    expect(collect($result['data'])->pluck('id'))->not->toContain($otherStudent->id);
});

it('runs a bounded number of queries across multiple students (no N+1)', function () {
    $campus = Campus::factory()->create();
    $students = Student::factory()->count(3)->create([
        'campus_id' => $campus->id,
        'intake' => 1,
        'intake_semester_id' => Semester::factory(),
    ]);
    foreach ($students as $student) {
        $unit = Unit::factory()->create(['unit_type' => 'egc']);
        AcademicRecord::factory()->create(['course_offering_id' => CourseOffering::factory(), 'student_id' => $student->id, 'unit_id' => $unit->id, 'is_passed' => true]);
    }

    $queryCount = 0;
    DB::listen(function () use (&$queryCount): void {
        $queryCount++;
    });

    app(GetStudentCompletedUnitsQuery::class)->handle(['campus_id' => $campus->id]);

    // count + students select + program eager load + passed records + required-credits batch.
    expect($queryCount)->toBeLessThanOrEqual(5);
});

it('reports total required curriculum credits, batched once per curriculum version', function () {
    $campus = Campus::factory()->create();
    $curriculumVersion = CurriculumVersion::factory()->create();
    $student = Student::factory()->create([
        'campus_id' => $campus->id,
        'curriculum_version_id' => $curriculumVersion->id,
        'intake' => 1,
        'intake_semester_id' => Semester::factory(),
    ]);

    $unitA = Unit::factory()->create(['credit_points' => 6]);
    $unitB = Unit::factory()->create(['credit_points' => 4]);
    CurriculumUnit::factory()->create(['curriculum_version_id' => $curriculumVersion->id, 'unit_id' => $unitA->id]);
    CurriculumUnit::factory()->create(['curriculum_version_id' => $curriculumVersion->id, 'unit_id' => $unitB->id]);

    $result = app(GetStudentCompletedUnitsQuery::class)->handle(['campus_id' => $campus->id]);
    $row = collect($result['data'])->firstWhere('id', $student->id);

    expect((float) $row['credits_required'])->toBe(10.0);
});

it('reports zero required credits when the curriculum version has no units assigned', function () {
    $campus = Campus::factory()->create();
    $emptyCurriculum = CurriculumVersion::factory()->create();
    $student = Student::factory()->create([
        'campus_id' => $campus->id,
        'curriculum_version_id' => $emptyCurriculum->id,
        'intake' => 1,
        'intake_semester_id' => Semester::factory(),
    ]);

    $result = app(GetStudentCompletedUnitsQuery::class)->handle(['campus_id' => $campus->id]);
    $row = collect($result['data'])->firstWhere('id', $student->id);

    expect((float) $row['credits_required'])->toBe(0.0);
});
