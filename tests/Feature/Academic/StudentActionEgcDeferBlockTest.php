<?php

declare(strict_types=1);

use App\Enums\StudentActionType;
use App\Models\Campus;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentActionLog;
use App\Models\User;
use App\Modules\Academic\Actions\RecordStudentActionAction;
use App\Modules\Academic\Exports\StudentActionLogsExport;
use App\Modules\Academic\Queries\ListStudentActionLogsQuery;
use App\Modules\Academic\Support\StudentActionExcelRowMapper;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function studentActionEgcFixture(): array
{
    $campus = Campus::factory()->create();
    $program = Program::factory()->create();
    $spring = Semester::factory()->create([
        'code' => '2026SP',
        'name' => 'Spring 2026',
        'start_date' => '2026-01-01',
        'end_date' => '2026-05-31',
    ]);
    $fall = Semester::factory()->create([
        'code' => '2026FA',
        'name' => 'Fall 2026',
        'start_date' => '2026-08-01',
        'end_date' => '2026-12-31',
    ]);
    $user = User::factory()->create();

    return compact('campus', 'program', 'spring', 'fall', 'user');
}

function studentActionStudent(Campus $campus, Program $program, Semester $semester, string $code, string $status): Student
{
    return Student::factory()
        ->forCampus($campus)
        ->forProgram($program)
        ->create([
            'student_id' => $code,
            'status' => $status,
            'intake' => 1,
            'intake_semester_id' => $semester->id,
        ]);
}

function studentActionLog(
    Student $student,
    User $user,
    Semester $fromSemester,
    Semester $returnSemester,
    ?int $egcBlock,
): StudentActionLog {
    return StudentActionLog::query()->create([
        'student_id' => $student->id,
        'action_type' => StudentActionType::ACADEMIC_DEFER->value,
        'reason' => 'Report fixture',
        'changed_by_user_id' => $user->id,
        'from_semester_id' => $fromSemester->id,
        'return_semester_id' => $returnSemester->id,
        'egc_defer_from_block_number' => $egcBlock,
        'previous_status' => $egcBlock ? 'intake_pre_uni_gc' : 'intake_course',
        'new_status' => 'deferred',
    ]);
}

it('persists egc defer block and keeps major defer block empty', function () {
    ['campus' => $campus, 'program' => $program, 'spring' => $spring, 'fall' => $fall, 'user' => $user] = studentActionEgcFixture();
    $egcStudent = studentActionStudent($campus, $program, $spring, 'EGC100001', 'intake_pre_uni_gc');
    $majorStudent = studentActionStudent($campus, $program, $spring, 'MAJ100001', 'intake_course');

    $egcLog = RecordStudentActionAction::run([
        'student_id' => $egcStudent->id,
        'action_type' => StudentActionType::ACADEMIC_DEFER->value,
        'reason' => 'Defer from block 2',
        'changed_by_user_id' => $user->id,
        'from_semester_id' => $spring->id,
        'return_semester_id' => $fall->id,
        'egc_defer_from_block_number' => 2,
        'defer_scope_type' => 'FULL',
        'defer_fee_policy' => 'FORFEIT',
    ]);

    $majorLog = RecordStudentActionAction::run([
        'student_id' => $majorStudent->id,
        'action_type' => StudentActionType::ACADEMIC_DEFER->value,
        'reason' => 'Major defer',
        'changed_by_user_id' => $user->id,
        'from_semester_id' => $spring->id,
        'return_semester_id' => $fall->id,
        'defer_scope_type' => 'FULL',
        'defer_fee_policy' => 'FORFEIT',
    ]);

    expect($egcLog->fresh()->egc_defer_from_block_number)->toBe(2)
        ->and($majorLog->fresh()->egc_defer_from_block_number)->toBeNull();
});

it('requires an egc defer block for egc students', function () {
    ['campus' => $campus, 'program' => $program, 'spring' => $spring, 'fall' => $fall, 'user' => $user] = studentActionEgcFixture();
    $egcStudent = studentActionStudent($campus, $program, $spring, 'EGC100002', 'intake_pre_uni_gc');

    RecordStudentActionAction::run([
        'student_id' => $egcStudent->id,
        'action_type' => StudentActionType::ACADEMIC_DEFER->value,
        'reason' => 'Missing block',
        'changed_by_user_id' => $user->id,
        'from_semester_id' => $spring->id,
        'return_semester_id' => $fall->id,
        'defer_scope_type' => 'FULL',
        'defer_fee_policy' => 'FORFEIT',
    ]);
})->throws(ValidationException::class, 'EGC defer from block is required for EGC students.');

it('filters student action reports by from semester and egc block', function () {
    ['campus' => $campus, 'program' => $program, 'spring' => $spring, 'fall' => $fall, 'user' => $user] = studentActionEgcFixture();
    $student = studentActionStudent($campus, $program, $spring, 'EGC100003', 'intake_pre_uni_gc');
    $otherStudent = studentActionStudent($campus, $program, $spring, 'EGC100004', 'intake_pre_uni_gc');
    $returnOnlyStudent = studentActionStudent($campus, $program, $spring, 'EGC100005', 'intake_pre_uni_gc');

    $included = studentActionLog($student, $user, $spring, $fall, 2);
    studentActionLog($otherStudent, $user, $spring, $fall, 1);
    studentActionLog($returnOnlyStudent, $user, $fall, $spring, 2);

    $ids = (new ListStudentActionLogsQuery)
        ->getBuilder([
            'from_semester_id' => $spring->id,
            'egc_defer_from_block_number' => 2,
        ])
        ->pluck('id')
        ->all();

    expect($ids)->toBe([$included->id]);
});

it('filters student action reports by the students current campus', function () {
    ['campus' => $campus, 'program' => $program, 'spring' => $spring, 'fall' => $fall, 'user' => $user] = studentActionEgcFixture();
    $otherCampus = Campus::factory()->create();
    $currentCampusStudent = studentActionStudent($campus, $program, $spring, 'EGC100008', 'intake_pre_uni_gc');
    $otherCampusStudent = studentActionStudent($otherCampus, $program, $spring, 'EGC100009', 'intake_pre_uni_gc');

    $included = studentActionLog($currentCampusStudent, $user, $spring, $fall, 1);
    studentActionLog($otherCampusStudent, $user, $spring, $fall, 1)->update([
        'from_campus_id' => $campus->id,
        'to_campus_id' => $campus->id,
    ]);

    $ids = (new ListStudentActionLogsQuery)
        ->getBuilder(['student_campus_id' => $campus->id])
        ->pluck('id')
        ->all();

    expect($ids)->toBe([$included->id]);
});

it('includes egc block in student action export rows', function () {
    ['campus' => $campus, 'program' => $program, 'spring' => $spring, 'fall' => $fall, 'user' => $user] = studentActionEgcFixture();
    $student = studentActionStudent($campus, $program, $spring, 'EGC100006', 'intake_pre_uni_gc');
    $log = studentActionLog($student, $user, $spring, $fall, 2)
        ->load(['student', 'changedBy', 'fromSemester', 'returnSemester']);
    $export = new StudentActionLogsExport(StudentActionLog::query());

    expect($export->headings())->toContain('EGC From Block')
        ->and($export->map($log))->toContain('Block 2');
});

it('rejects from semester before the active semester on store', function () {
    ['campus' => $campus, 'program' => $program, 'user' => $user] = studentActionEgcFixture();

    Semester::query()->update(['is_active' => false]);

    $pastSemester = Semester::factory()->create([
        'code' => '2025SP',
        'name' => 'Spring 2025',
        'start_date' => '2025-01-01',
        'end_date' => '2025-05-31',
        'is_active' => false,
    ]);
    $activeSemester = Semester::factory()->active()->create([
        'code' => '2026SP',
        'name' => 'Spring 2026',
        'start_date' => '2026-01-01',
        'end_date' => '2026-05-31',
    ]);
    $futureSemester = Semester::factory()->create([
        'code' => '2026FA',
        'name' => 'Fall 2026',
        'start_date' => '2026-08-01',
        'end_date' => '2026-12-31',
        'is_active' => false,
    ]);

    $rejectedStudent = studentActionStudent($campus, $program, $activeSemester, 'EGC100010', 'intake_pre_uni_gc');
    $acceptedStudent = studentActionStudent($campus, $program, $activeSemester, 'EGC100011', 'intake_pre_uni_gc');
    $futureStudent = studentActionStudent($campus, $program, $activeSemester, 'EGC100012', 'intake_pre_uni_gc');

    session([
        '_token' => 'student-action-from-semester-csrf',
        'current_campus_id' => $campus->id,
    ]);

    $permissionService = Mockery::mock(PermissionService::class);
    $permissionService->shouldReceive('getUserPermissions')
        ->andReturn(['view_student_action', 'change_student_status']);
    app()->singleton(PermissionService::class, fn () => $permissionService);

    actingAs($user)
        ->post(route('students.actions.store', ['student' => $rejectedStudent->id]), [
            '_token' => 'student-action-from-semester-csrf',
            'student_id' => $rejectedStudent->id,
            'action_type' => StudentActionType::WAITING_COURSE_OPENING->value,
            'reason' => 'Waiting for course opening',
            'from_semester_id' => $pastSemester->id,
            'egc_defer_from_block_number' => 1,
        ])
        ->assertSessionHasErrors(['from_semester_id']);

    actingAs($user)
        ->post(route('students.actions.store', ['student' => $acceptedStudent->id]), [
            '_token' => 'student-action-from-semester-csrf',
            'student_id' => $acceptedStudent->id,
            'action_type' => StudentActionType::WAITING_COURSE_OPENING->value,
            'reason' => 'Waiting for course opening',
            'from_semester_id' => $activeSemester->id,
            'egc_defer_from_block_number' => 1,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    actingAs($user)
        ->post(route('students.actions.store', ['student' => $futureStudent->id]), [
            '_token' => 'student-action-from-semester-csrf',
            'student_id' => $futureStudent->id,
            'action_type' => StudentActionType::WAITING_COURSE_OPENING->value,
            'reason' => 'Waiting for course opening future',
            'from_semester_id' => $futureSemester->id,
            'egc_defer_from_block_number' => 2,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();
});

it('maps current and legacy import rows with egc block defaulting to block 1', function () {
    ['campus' => $campus, 'program' => $program, 'spring' => $spring, 'fall' => $fall] = studentActionEgcFixture();
    studentActionStudent($campus, $program, $spring, 'EGC100007', 'intake_pre_uni_gc');

    /** @var StudentActionExcelRowMapper $mapper */
    $mapper = app(StudentActionExcelRowMapper::class);

    $currentHeader = $mapper->validateHeaders(StudentActionExcelRowMapper::EXPECTED_HEADERS);
    $currentRow = [
        'EGC100007', 'ACADEMIC_DEFER', 'Defer by import', '2026SP', '2026FA',
        'yes', '', '', '', '', '', '', '', '', '', '',
    ];
    $currentMapped = $mapper->mapRow($currentRow, 2, $currentHeader['format']);

    $legacyHeader = $mapper->validateHeaders(StudentActionExcelRowMapper::LEGACY_EXPECTED_HEADERS);
    $legacyRow = [
        'EGC100007', 'ACADEMIC_DEFER', 'Legacy defer', '2026SP', '2026FA',
        'yes', '', '', '', '', '', '', '', '', '',
    ];
    $legacyMapped = $mapper->mapRow($legacyRow, 3, $legacyHeader['format']);

    expect($currentHeader)->toMatchArray(['ok' => true, 'format' => 'current'])
        ->and($currentMapped['status'])->toBe('valid')
        ->and($currentMapped['normalized_payload']['egc_defer_from_block_number'])->toBe(1)
        ->and($legacyHeader)->toMatchArray(['ok' => true, 'format' => 'legacy'])
        ->and($legacyMapped['status'])->toBe('valid')
        ->and($legacyMapped['normalized_payload']['egc_defer_from_block_number'])->toBe(1);
});
