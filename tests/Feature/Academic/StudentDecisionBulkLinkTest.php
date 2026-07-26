<?php

declare(strict_types=1);

use App\Enums\StudentActionType;
use App\Models\Campus;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentActionLog;
use App\Models\StudentDecision;
use App\Models\User;
use App\Shared\Contracts\Identity\CampusPermissionReader;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

const STUDENT_DECISION_BULK_LINK_CSRF = 'student-decision-bulk-link-csrf';

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->campus = Campus::factory()->create();
    $this->otherCampus = Campus::factory()->create();
    $this->program = Program::factory()->create();
    $this->semester = Semester::factory()->create();

    session([
        '_token' => STUDENT_DECISION_BULK_LINK_CSRF,
        'current_campus_id' => $this->campus->id,
    ]);

    $permissionService = Mockery::mock(CampusPermissionReader::class);
    $permissionService->shouldReceive('permissionCodesForUserId')
        ->andReturn(['view_student_action', 'change_student_status']);

    app()->singleton(CampusPermissionReader::class, fn () => $permissionService);

    $this->decision = studentDecisionBulkLinkDecision($this->user, 'QD-BULK-001');
});

function studentDecisionBulkLinkDecision(User $user, string $number): StudentDecision
{
    return StudentDecision::query()->create([
        'decision_name' => 'Bulk link decision',
        'decision_number' => $number,
        'decision_signer' => 'Academic Office',
        'issued_at' => '2026-05-01',
        'expires_at' => null,
        'changed_by_user_id' => $user->id,
    ]);
}

function studentDecisionBulkLinkStudent(Campus $campus, Program $program, Semester $semester, string $code): Student
{
    return Student::factory()
        ->forCampus($campus)
        ->forProgram($program)
        ->create([
            'student_id' => $code,
            'intake' => 1,
            'intake_semester_id' => $semester->id,
        ]);
}

function studentDecisionBulkLinkAction(Student $student, User $user, ?StudentDecision $decision = null, StudentActionType $actionType = StudentActionType::ACADEMIC_DROPOUT): StudentActionLog
{
    return StudentActionLog::query()->create([
        'student_id' => $student->id,
        'action_type' => $actionType->value,
        'reason' => 'Bulk link fixture',
        'changed_by_user_id' => $user->id,
        'decision_id' => $decision?->id,
    ]);
}

it('previews matched students, unmatched codes, and linkable action logs in campus scope', function () {
    $studentWithAction = studentDecisionBulkLinkStudent($this->campus, $this->program, $this->semester, 'SE900001');
    $studentWithoutAction = studentDecisionBulkLinkStudent($this->campus, $this->program, $this->semester, 'SE900002');
    $otherCampusStudent = studentDecisionBulkLinkStudent($this->otherCampus, $this->program, $this->semester, 'SE900003');

    $action = studentDecisionBulkLinkAction($studentWithAction, $this->user);
    studentDecisionBulkLinkAction($studentWithoutAction, $this->user, actionType: StudentActionType::STUDENT_MAJOR_ENROLLMENT);
    studentDecisionBulkLinkAction($otherCampusStudent, $this->user);

    actingAs($this->user)
        ->withHeader('X-CSRF-TOKEN', STUDENT_DECISION_BULK_LINK_CSRF)
        ->withSession([
            '_token' => STUDENT_DECISION_BULK_LINK_CSRF,
            'current_campus_id' => $this->campus->id,
        ])
        ->postJson(route('reports.student-decisions.students.preview', $this->decision), [
            'action_type' => StudentActionType::ACADEMIC_DROPOUT->value,
            'student_codes' => "SE900001\nSE900002\nSE900003\nSE999999\nSE900001",
        ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.summary.input_count', 5)
        ->assertJsonPath('data.summary.unique_count', 4)
        ->assertJsonPath('data.summary.duplicate_count', 1)
        ->assertJsonPath('data.summary.matched_count', 2)
        ->assertJsonPath('data.summary.unmatched_count', 2)
        ->assertJsonPath('data.summary.linkable_action_count', 1)
        ->assertJsonPath('data.students.0.student.student_id', 'SE900001')
        ->assertJsonPath('data.students.0.action_logs.0.id', $action->id)
        ->assertJsonPath('data.students.1.student.student_id', 'SE900002')
        ->assertJsonPath('data.students.1.skipped_reason', 'No matching student action logs found.')
        ->assertJsonPath('data.unmatched_codes.0', 'SE900003')
        ->assertJsonPath('data.unmatched_codes.1', 'SE999999');
});

it('links only unlinked current-campus action logs to the decision', function () {
    $student = studentDecisionBulkLinkStudent($this->campus, $this->program, $this->semester, 'SE910001');
    $otherCampusStudent = studentDecisionBulkLinkStudent($this->otherCampus, $this->program, $this->semester, 'SE910002');
    $otherDecision = studentDecisionBulkLinkDecision($this->user, 'QD-OTHER-001');

    $firstAction = studentDecisionBulkLinkAction($student, $this->user);
    $secondAction = studentDecisionBulkLinkAction($student, $this->user);
    $alreadyLinkedAction = studentDecisionBulkLinkAction($student, $this->user, $otherDecision);
    $wrongTypeAction = studentDecisionBulkLinkAction($student, $this->user, actionType: StudentActionType::STUDENT_MAJOR_ENROLLMENT);
    $otherCampusAction = studentDecisionBulkLinkAction($otherCampusStudent, $this->user);

    actingAs($this->user)
        ->withHeader('X-CSRF-TOKEN', STUDENT_DECISION_BULK_LINK_CSRF)
        ->withSession([
            '_token' => STUDENT_DECISION_BULK_LINK_CSRF,
            'current_campus_id' => $this->campus->id,
        ])
        ->postJson(route('reports.student-decisions.students.bulk-link', $this->decision), [
            'action_type' => StudentActionType::ACADEMIC_DROPOUT->value,
            'student_codes' => "SE910001\nSE910002",
        ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.linked_action_count', 2)
        ->assertJsonPath('data.requested_action_count', 2);

    expect($firstAction->fresh()->decision_id)->toBe($this->decision->id)
        ->and($secondAction->fresh()->decision_id)->toBe($this->decision->id)
        ->and($alreadyLinkedAction->fresh()->decision_id)->toBe($otherDecision->id)
        ->and($wrongTypeAction->fresh()->decision_id)->toBeNull()
        ->and($otherCampusAction->fresh()->decision_id)->toBeNull();
});

it('rejects more than one hundred unique student codes', function () {
    $studentCodes = collect(range(1, 101))
        ->map(fn (int $index): string => sprintf('SE%06d', $index))
        ->implode("\n");

    actingAs($this->user)
        ->withHeader('X-CSRF-TOKEN', STUDENT_DECISION_BULK_LINK_CSRF)
        ->withSession([
            '_token' => STUDENT_DECISION_BULK_LINK_CSRF,
            'current_campus_id' => $this->campus->id,
        ])
        ->postJson(route('reports.student-decisions.students.preview', $this->decision), [
            'action_type' => StudentActionType::ACADEMIC_DROPOUT->value,
            'student_codes' => $studentCodes,
        ])
        ->assertUnprocessable()
        ->assertJsonPath('errors.0.field', 'student_codes');
});
