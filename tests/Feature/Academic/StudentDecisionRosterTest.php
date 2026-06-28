<?php

declare(strict_types=1);

use App\Enums\AcademicProgressionEventType;
use App\Enums\StudentActionType;
use App\Models\Campus;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentActionLog;
use App\Models\StudentDecision;
use App\Models\User;
use App\Modules\Academic\Actions\BackfillDecisionStudentRosterAction;
use App\Modules\Academic\Actions\BulkLinkStudentsToDecisionAction;
use App\Modules\Academic\Actions\RecordStudentActionAction;
use App\Modules\Academic\Queries\GetMissingDecisionReportQuery;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->campus = Campus::factory()->create();
    $this->otherCampus = Campus::factory()->create();
    $this->program = Program::factory()->create();
    $this->semester = Semester::factory()->create();
});

function rosterStudent(Campus $campus, Program $program, Semester $semester, string $code, string $status = 'intake_course'): Student
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

function rosterDecision(User $user, string $number): StudentDecision
{
    return StudentDecision::query()->create([
        'decision_name' => 'Roster decision',
        'decision_number' => $number,
        'decision_signer' => 'Academic Office',
        'issued_at' => '2026-05-01',
        'changed_by_user_id' => $user->id,
    ]);
}

it('classifies which transitions require a decision', function () {
    expect(StudentActionType::ACADEMIC_DEFER->requiresDecision())->toBeTrue()
        ->and(StudentActionType::ACADEMIC_RESUME->requiresDecision())->toBeTrue()
        ->and(StudentActionType::ACADEMIC_DROPOUT->requiresDecision())->toBeTrue()
        ->and(StudentActionType::CAMPUS_TRANSFER->requiresDecision())->toBeTrue()
        ->and(StudentActionType::ADMISSION_DEFERRAL->requiresDecision())->toBeFalse()
        ->and(StudentActionType::STUDENT_ENROLLMENT_NE->requiresDecision())->toBeFalse()
        // EGC progression events never require a Decision (ADR-0008, revised):
        // placement / stage change are enrolment milestones, like the enrolment
        // actions, not governed transitions.
        ->and(AcademicProgressionEventType::PLACEMENT_INITIALIZED->requiresDecision())->toBeFalse()
        ->and(AcademicProgressionEventType::COURSE_STAGE_CHANGED->requiresDecision())->toBeFalse()
        ->and(AcademicProgressionEventType::ENGLISH_LEVEL_CHANGED->requiresDecision())->toBeFalse()
        ->and(AcademicProgressionEventType::IELTS_RECORDED->requiresDecision())->toBeFalse()
        ->and(AcademicProgressionEventType::requiresDecisionValues())->toBe([])
        ->and(StudentActionType::requiresDecisionValues())->toEqualCanonicalizing([
            StudentActionType::ACADEMIC_DEFER->value,
            StudentActionType::ACADEMIC_RESUME->value,
            StudentActionType::ACADEMIC_DROPOUT->value,
            StudentActionType::CAMPUS_TRANSFER->value,
        ]);
});

it('records a requires-decision action with no decision and surfaces it in the missing-decision report', function () {
    $student = rosterStudent($this->campus, $this->program, $this->semester, 'SE800001');

    $actionLog = RecordStudentActionAction::run([
        'student_id' => $student->id,
        'action_type' => StudentActionType::ACADEMIC_DROPOUT->value,
        'reason' => 'Left the program',
        'changed_by_user_id' => $this->user->id,
        'dropout_semester_id' => $this->semester->id,
    ]);

    expect($actionLog->decision_id)->toBeNull()
        ->and($student->fresh()->decisions()->count())->toBe(0);

    $report = (new GetMissingDecisionReportQuery)->handle([], $this->campus->id);

    expect($report->total())->toBe(1)
        ->and($report->items()[0]['id'])->toBe('action-'.$actionLog->id);
});

it('covers the student on the decision roster when an action is authorized up front', function () {
    $student = rosterStudent($this->campus, $this->program, $this->semester, 'SE800002');
    $decision = rosterDecision($this->user, 'QD-800-002');

    $actionLog = RecordStudentActionAction::run([
        'student_id' => $student->id,
        'action_type' => StudentActionType::ACADEMIC_DROPOUT->value,
        'reason' => 'Left the program',
        'changed_by_user_id' => $this->user->id,
        'dropout_semester_id' => $this->semester->id,
        'decision_id' => $decision->id,
    ]);

    expect($actionLog->decision_id)->toBe($decision->id)
        ->and($decision->students()->where('students.id', $student->id)->exists())->toBeTrue()
        ->and((new GetMissingDecisionReportQuery)->handle([], $this->campus->id)->total())->toBe(0);
});

it('covers many students on one decision roster through bulk link', function () {
    $decision = rosterDecision($this->user, 'QD-800-003');

    $first = rosterStudent($this->campus, $this->program, $this->semester, 'SE800010');
    $second = rosterStudent($this->campus, $this->program, $this->semester, 'SE800011');

    foreach ([$first, $second] as $student) {
        StudentActionLog::query()->create([
            'student_id' => $student->id,
            'action_type' => StudentActionType::ACADEMIC_DROPOUT->value,
            'reason' => 'Bulk link fixture',
            'changed_by_user_id' => $this->user->id,
        ]);
    }

    $result = BulkLinkStudentsToDecisionAction::run(
        $decision,
        ['SE800010', 'SE800011'],
        StudentActionType::ACADEMIC_DROPOUT->value,
        $this->user->id,
        $this->campus->id,
    );

    expect($result['linked_action_count'])->toBe(2)
        ->and($decision->students()->count())->toBe(2)
        ->and($decision->students()->pluck('students.id')->sort()->values()->all())
        ->toBe(collect([$first->id, $second->id])->sort()->values()->all());
});

it('represents an informational decision as a roster with no authorized event', function () {
    $decision = rosterDecision($this->user, 'QD-800-004');
    $first = rosterStudent($this->campus, $this->program, $this->semester, 'SE800020');
    $second = rosterStudent($this->campus, $this->program, $this->semester, 'SE800021');

    $decision->cover($first->id, $second->id);

    expect($decision->students()->count())->toBe(2)
        ->and($decision->actionLogs()->count())->toBe(0)
        ->and($decision->progressionEvents()->count())->toBe(0);
});

it('backfills the roster from existing action-log links without losing them and is idempotent', function () {
    $decision = rosterDecision($this->user, 'QD-800-005');
    $first = rosterStudent($this->campus, $this->program, $this->semester, 'SE800030');
    $second = rosterStudent($this->campus, $this->program, $this->semester, 'SE800031');

    // Legacy-shaped data: action logs linked to a decision, but no roster rows yet.
    foreach ([$first, $second] as $student) {
        StudentActionLog::query()->create([
            'student_id' => $student->id,
            'action_type' => StudentActionType::ACADEMIC_DROPOUT->value,
            'reason' => 'Legacy link',
            'changed_by_user_id' => $this->user->id,
            'decision_id' => $decision->id,
        ]);
    }

    DB::table('student_decision_student')->delete();

    $inserted = BackfillDecisionStudentRosterAction::run();

    expect($inserted)->toBe(2)
        ->and($decision->students()->count())->toBe(2)
        ->and(StudentActionLog::query()->where('decision_id', $decision->id)->count())->toBe(2);

    // Idempotent: a second run inserts nothing and leaves the roster unchanged.
    expect(BackfillDecisionStudentRosterAction::run())->toBe(0)
        ->and($decision->students()->count())->toBe(2);
});

it('renders the missing-decision report page through the gated route', function () {
    $student = rosterStudent($this->campus, $this->program, $this->semester, 'SE800040');
    StudentActionLog::query()->create([
        'student_id' => $student->id,
        'action_type' => StudentActionType::ACADEMIC_DROPOUT->value,
        'reason' => 'Report row',
        'changed_by_user_id' => $this->user->id,
    ]);

    $permissionService = Mockery::mock(PermissionService::class);
    $permissionService->shouldReceive('getUserPermissions')->andReturn(['view_student_action']);
    app()->singleton(PermissionService::class, fn () => $permissionService);

    actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->get(route('reports.academic-progression.missing-decisions'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Admin/Reports/AcademicProgressionAudit/MissingDecisions')
            ->has('transitions.data', 1)
            ->where('transitions.data.0.student.student_id', 'SE800040')
            ->where('transitions.data.0.transition_type', StudentActionType::ACADEMIC_DROPOUT->value)
        );
});
