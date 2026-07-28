<?php

declare(strict_types=1);

use App\Enums\StudentActionType;
use App\Models\Campus;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentActionLog;
use App\Models\User;
use App\Modules\Academic\Progression\Queries\Reporting\GetStudentLifecycleCohortMatrixQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The matrix replaces a report whose columns silently disagreed: a student could
 * be counted in an event column, be missing from every status column, and have
 * their defer attributed to a year that had no row. These invariants are what
 * make that class of drift impossible.
 */
function matrixSemesters(): array
{
    // All in the past so the timeline includes them; the query stops at today.
    return [
        'fall2025' => Semester::factory()->create(['code' => 'FALL2025', 'name' => 'Fall 2025', 'start_date' => '2025-09-22', 'end_date' => '2026-01-16']),
        'spring2026' => Semester::factory()->create(['code' => 'SPRING2026', 'name' => 'Spring 2026', 'start_date' => '2026-01-19', 'end_date' => '2026-05-22']),
        'summer2026' => Semester::factory()->create(['code' => 'SUMMER2026', 'name' => 'Summer 2026', 'start_date' => '2026-05-04', 'end_date' => '2026-08-31']),
    ];
}

/** `$ne` mirrors the report's NE definition: a student with an AP account. */
function matrixStudent(Campus $campus, Semester $intake, bool $ne = true): Student
{
    return Student::factory()->create([
        'campus_id' => $campus->id,
        'intake_semester_id' => $intake->id,
        'intake' => 2025,
        'user_id' => $ne ? User::factory()->create()->id : null,
        'status' => 'intake_course',
    ]);
}

/** @param array<string, mixed> $overrides */
function matrixLog(Student $student, StudentActionType $type, array $overrides): StudentActionLog
{
    return StudentActionLog::create([
        'student_id' => $student->id,
        'action_type' => $type->value,
        'reason' => 'test',
        'changed_by_user_id' => User::factory()->create()->id,
        ...$overrides,
    ]);
}

function matrixAction(Student $student, StudentActionType $type, Semester $anchor, string $from, string $to): StudentActionLog
{
    return matrixLog($student, $type, [
        'from_semester_id' => $anchor->id,
        'previous_status' => $from,
        'new_status' => $to,
    ]);
}

it('keeps every semester column summing to the cohort headcount', function () {
    $campus = Campus::factory()->create();
    $sem = matrixSemesters();

    $staying = matrixStudent($campus, $sem['fall2025']);
    $deferring = matrixStudent($campus, $sem['fall2025']);

    matrixAction($staying, StudentActionType::STUDENT_MAJOR_ENROLLMENT, $sem['fall2025'], 'pending', 'intake_course');
    matrixAction($deferring, StudentActionType::STUDENT_MAJOR_ENROLLMENT, $sem['fall2025'], 'pending', 'intake_course');
    matrixAction($deferring, StudentActionType::ACADEMIC_DEFER, $sem['summer2026'], 'intake_course', 'deferred');

    $result = app(GetStudentLifecycleCohortMatrixQuery::class)->handle();
    $cohort = $result['cohorts'][0];

    expect($cohort['size'])->toBe(2);

    foreach ($result['semesters'] as $semester) {
        expect(array_sum($cohort['cells'][$semester['id']]))
            ->toBe($cohort['size'], "column {$semester['code']} must account for every cohort member");
    }
});

it('places a defer in the semester it took effect, not the intake semester', function () {
    $campus = Campus::factory()->create();
    $sem = matrixSemesters();

    $student = matrixStudent($campus, $sem['fall2025']);
    matrixAction($student, StudentActionType::STUDENT_MAJOR_ENROLLMENT, $sem['fall2025'], 'pending', 'intake_course');
    matrixAction($student, StudentActionType::ACADEMIC_DEFER, $sem['summer2026'], 'intake_course', 'deferred');

    $cells = app(GetStudentLifecycleCohortMatrixQuery::class)->handle()['cohorts'][0]['cells'];

    expect($cells[$sem['fall2025']->id]['intake_course'])->toBe(1)
        ->and($cells[$sem['spring2026']->id]['intake_course'])->toBe(1)
        ->and($cells[$sem['summer2026']->id]['deferred'])->toBe(1)
        ->and($cells[$sem['summer2026']->id]['intake_course'] ?? 0)->toBe(0);
});

it('never reports NE above the cohort headcount', function () {
    $campus = Campus::factory()->create();
    $sem = matrixSemesters();

    matrixStudent($campus, $sem['fall2025'], ne: true);
    matrixStudent($campus, $sem['fall2025'], ne: false);

    $result = app(GetStudentLifecycleCohortMatrixQuery::class)->handle();

    expect($result['cohorts'][0]['ne'])->toBe(1)
        ->and($result['cohorts'][0]['ne'])->toBeLessThanOrEqual($result['cohorts'][0]['size'])
        ->and($result['totals']['ne'])->toBeLessThanOrEqual($result['totals']['size']);
});

it('derives the event margin from the difference between columns', function () {
    $campus = Campus::factory()->create();
    $sem = matrixSemesters();

    $student = matrixStudent($campus, $sem['fall2025']);
    matrixAction($student, StudentActionType::STUDENT_MAJOR_ENROLLMENT, $sem['fall2025'], 'pending', 'intake_course');
    matrixAction($student, StudentActionType::ACADEMIC_DEFER, $sem['summer2026'], 'intake_course', 'deferred');

    $result = app(GetStudentLifecycleCohortMatrixQuery::class)->handle();
    $events = collect($result['events'])->keyBy('semester_id');

    expect($events[$sem['fall2025']->id]['entered'])->toBe(1)
        ->and($events[$sem['spring2026']->id]['moved_in'])->toBe([])
        ->and($events[$sem['summer2026']->id]['moved_in']['deferred'])->toBe(1);
});

it('rates a cohort against its own NE population', function () {
    $campus = Campus::factory()->create();
    $sem = matrixSemesters();

    $deferring = matrixStudent($campus, $sem['fall2025']);
    matrixStudent($campus, $sem['fall2025']);
    matrixStudent($campus, $sem['fall2025']);
    matrixStudent($campus, $sem['fall2025']);

    matrixAction($deferring, StudentActionType::ACADEMIC_DEFER, $sem['summer2026'], 'intake_course', 'deferred');

    $cohort = app(GetStudentLifecycleCohortMatrixQuery::class)->handle()['cohorts'][0];

    // One of four NE students deferred — the numerator and the denominator both
    // belong to this cohort, which the year-keyed report could not guarantee.
    expect($cohort['ne'])->toBe(4)
        ->and($cohort['ever_deferred'])->toBe(1)
        ->and($cohort['df_rate'])->toBe(25.0);
});

it('anchors an enrolment that only filled effective_semester_id', function () {
    // Production enrolment logs leave from_semester_id null and carry the term
    // in effective_semester_id instead. Reading the type-specific column alone
    // would drop these actions and leave the whole cohort stuck on "pending".
    $campus = Campus::factory()->create();
    $sem = matrixSemesters();

    $student = matrixStudent($campus, $sem['fall2025']);
    matrixLog($student, StudentActionType::STUDENT_MAJOR_ENROLLMENT, [
        'from_semester_id' => null,
        'effective_semester_id' => $sem['fall2025']->id,
        'previous_status' => 'pending',
        'new_status' => 'intake_course',
    ]);

    $cohort = app(GetStudentLifecycleCohortMatrixQuery::class)->handle()['cohorts'][0];

    expect($cohort['cells'][$sem['fall2025']->id]['intake_course'])->toBe(1)
        ->and($cohort['cells'][$sem['fall2025']->id]['pending'] ?? 0)->toBe(0);
});

it('reproduces the production cohort that the year-keyed report got wrong', function () {
    // 10 students entered FALL2025; 7 into a major, 3 into pre-uni GC. Two of the
    // pre-uni students deferred from SUMMER2026 — the defers the old report lost
    // because it keyed them to 2026 while every row was keyed to intake year 2025.
    $campus = Campus::factory()->create();
    $sem = matrixSemesters();

    $enrolLog = fn (Student $s, string $status) => matrixLog($s, StudentActionType::STUDENT_MAJOR_ENROLLMENT, [
        'from_semester_id' => null,
        'effective_semester_id' => $sem['fall2025']->id,
        'previous_status' => 'pending',
        'new_status' => $status,
    ]);

    for ($i = 0; $i < 7; $i++) {
        $enrolLog(matrixStudent($campus, $sem['fall2025']), 'intake_course');
    }

    $preUni = [];
    for ($i = 0; $i < 3; $i++) {
        $student = matrixStudent($campus, $sem['fall2025']);
        $enrolLog($student, 'intake_pre_uni_gc');
        $preUni[] = $student;
    }

    matrixAction($preUni[0], StudentActionType::ACADEMIC_DEFER, $sem['summer2026'], 'intake_pre_uni_gc', 'deferred');
    matrixAction($preUni[1], StudentActionType::ACADEMIC_DEFER, $sem['summer2026'], 'intake_pre_uni_gc', 'deferred');

    $result = app(GetStudentLifecycleCohortMatrixQuery::class)->handle();
    $cohort = $result['cohorts'][0];

    expect($cohort['size'])->toBe(10)
        ->and($cohort['ne'])->toBe(10)
        ->and($cohort['cells'][$sem['fall2025']->id])->toBe(['intake_pre_uni_gc' => 3, 'intake_course' => 7, 'deferred' => 0])
        ->and($cohort['cells'][$sem['summer2026']->id])->toBe(['intake_pre_uni_gc' => 1, 'intake_course' => 7, 'deferred' => 2])
        ->and($cohort['ever_deferred'])->toBe(2)
        ->and($cohort['df_rate'])->toBe(20.0);

    // Every column still accounts for all ten — the check the old report failed,
    // where the status columns summed to 8 next to an NE of 10.
    foreach ($result['semesters'] as $semester) {
        expect(array_sum($cohort['cells'][$semester['id']]))->toBe(10);
    }
});

it('counts a returning student as ever deferred while showing them back in class', function () {
    $campus = Campus::factory()->create();
    $sem = matrixSemesters();

    $student = matrixStudent($campus, $sem['fall2025']);
    matrixAction($student, StudentActionType::STUDENT_MAJOR_ENROLLMENT, $sem['fall2025'], 'pending', 'intake_course');
    matrixAction($student, StudentActionType::ACADEMIC_DEFER, $sem['spring2026'], 'intake_course', 'deferred');

    matrixLog($student, StudentActionType::ACADEMIC_RESUME, [
        'return_semester_id' => $sem['summer2026']->id,
        'previous_status' => 'deferred',
        'new_status' => 'intake_course',
    ]);

    $cohort = app(GetStudentLifecycleCohortMatrixQuery::class)->handle()['cohorts'][0];

    expect($cohort['cells'][$sem['spring2026']->id]['deferred'])->toBe(1)
        ->and($cohort['cells'][$sem['summer2026']->id]['intake_course'])->toBe(1)
        ->and($cohort['ever_deferred'])->toBe(1)
        ->and($cohort['current']['intake_course'])->toBe(1);
});
