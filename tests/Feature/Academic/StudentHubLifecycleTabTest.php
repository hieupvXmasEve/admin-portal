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
use App\Modules\Academic\Actions\RecordStudentActionAction;
use App\Modules\Academic\Progression\Models\ProgramEnrollment;
use App\Services\PermissionService;
use App\Shared\Contracts\Finance\DTO\StudentLifecycleDeferData;
use App\Shared\Contracts\Finance\StudentLifecycleFinanceCommand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

const HUB_LIFECYCLE_CSRF = 'hub-lifecycle-csrf';

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->campus = Campus::factory()->create();
    $this->program = Program::factory()->create();
    $this->semester = Semester::factory()->create();
    $this->student = Student::factory()
        ->forCampus($this->campus)
        ->forProgram($this->program)
        ->create([
            'student_id' => 'SE820001',
            'status' => 'intake_pre_uni_gc',
            'intake' => 1,
            'intake_semester_id' => $this->semester->id,
        ]);

    session([
        '_token' => HUB_LIFECYCLE_CSRF,
        'current_campus_id' => $this->campus->id,
    ]);

    grantHubLifecyclePermissions(['view_student', 'view_student_summary', 'view_student_action', 'change_student_status']);
});

function grantHubLifecyclePermissions(array $permissions): void
{
    $permissionService = Mockery::mock(PermissionService::class);
    $permissionService->shouldReceive('getUserPermissions')->andReturn($permissions);
    app()->singleton(PermissionService::class, fn () => $permissionService);
}

it('renders the Lifecycle tab with the merged timeline and egc sub-panel', function () {
    StudentActionLog::query()->create([
        'student_id' => $this->student->id,
        'action_type' => StudentActionType::ACADEMIC_DROPOUT->value,
        'reason' => 'Smoke fixture',
        'changed_by_user_id' => $this->user->id,
    ]);

    actingAs($this->user)
        ->get(route('students.academic-summary.lifecycle', $this->student->id))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('students/AcademicSummary/Lifecycle')
            ->has('timeline', 1)
            ->has('egc')
            ->where('can_change_status', true)
            ->has('options.action')
            ->has('options.placement')
            ->has('options.decisions')
        );
});

it('exposes only the status-appropriate selectable action types to the record dialog', function () {
    actingAs($this->user)
        ->get(route('students.academic-summary.lifecycle', $this->student->id))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('options.action.allowedActionTypes', [
                StudentActionType::WAITING_COURSE_OPENING->value,
                StudentActionType::ACADEMIC_DEFER->value,
                StudentActionType::ACADEMIC_DROPOUT->value,
                StudentActionType::CAMPUS_TRANSFER->value,
            ])
        );
});

it('rejects recording an illogical transition (resume while intake_course) at the backend', function () {
    $courseStudent = Student::factory()
        ->forCampus($this->campus)
        ->forProgram($this->program)
        ->create([
            'student_id' => 'SE820002',
            'status' => 'intake_course',
            'intake' => 1,
            'intake_semester_id' => $this->semester->id,
        ]);

    expect(fn () => RecordStudentActionAction::run([
        'student_id' => $courseStudent->id,
        'action_type' => StudentActionType::ACADEMIC_RESUME->value,
        'reason' => 'Should be rejected',
        'changed_by_user_id' => $this->user->id,
        'return_semester_id' => $this->semester->id,
    ]))->toThrow(ValidationException::class);

    expect($courseStudent->fresh()->status)->toBe('intake_course');
});

it('writes the lifecycle stage to Program Enrollment without mutating Student identity state', function () {
    $legacyStudentStatus = $this->student->status;

    RecordStudentActionAction::run([
        'student_id' => $this->student->id,
        'action_type' => StudentActionType::WAITING_COURSE_OPENING->value,
        'reason' => 'Course opening is pending',
        'changed_by_user_id' => $this->user->id,
    ]);

    $enrollment = ProgramEnrollment::query()->sole();

    expect($enrollment->enrollment_status)->toBe('active')
        ->and($enrollment->study_stage)->toBe('pending_course_opening')
        ->and($this->student->fresh()->status)->toBe($legacyStudentStatus);
});

it('reactivates Program Enrollment without changing the Student identity snapshot', function () {
    $pendingStudent = Student::factory()
        ->forCampus($this->campus)
        ->forProgram($this->program)
        ->create([
            'student_id' => 'SE820003',
            'status' => 'pending',
            'academic_status' => 'active',
            'intake' => 1,
            'intake_semester_id' => $this->semester->id,
        ]);

    RecordStudentActionAction::run([
        'student_id' => $pendingStudent->id,
        'action_type' => StudentActionType::STUDENT_ENROLLMENT_NE->value,
        'reason' => 'Enrollment confirmed',
        'changed_by_user_id' => $this->user->id,
    ]);

    $enrollment = ProgramEnrollment::query()->where('student_id', $pendingStudent->id)->sole();

    expect($enrollment->enrollment_status)->toBe('active')
        ->and($enrollment->study_stage)->toBe('intake_pre_uni_gc')
        ->and($pendingStudent->fresh()->status)->toBe('pending');
});

it('resumes from the Progression-owned study stage while the Student snapshot stays unchanged', function () {
    RecordStudentActionAction::run([
        'student_id' => $this->student->id,
        'action_type' => StudentActionType::WAITING_COURSE_OPENING->value,
        'reason' => 'Course opening is pending',
        'changed_by_user_id' => $this->user->id,
    ]);

    RecordStudentActionAction::run([
        'student_id' => $this->student->id,
        'action_type' => StudentActionType::ACADEMIC_RESUME->value,
        'reason' => 'Course is available',
        'return_semester_id' => $this->semester->id,
        'changed_by_user_id' => $this->user->id,
    ]);

    $enrollment = ProgramEnrollment::query()->sole();
    $resumeLog = StudentActionLog::query()
        ->where('action_type', StudentActionType::ACADEMIC_RESUME->value)
        ->sole();

    expect($enrollment->enrollment_status)->toBe('active')
        ->and($enrollment->study_stage)->toBe('intake_pre_uni_gc')
        ->and($resumeLog->previous_status)->toBe('pending_course_opening')
        ->and($resumeLog->new_status)->toBe('intake_pre_uni_gc')
        ->and($this->student->fresh()->status)->toBe('intake_pre_uni_gc');
});

it('builds lifecycle staff options from Program Enrollment instead of the stale Student snapshot', function () {
    RecordStudentActionAction::run([
        'student_id' => $this->student->id,
        'action_type' => StudentActionType::WAITING_COURSE_OPENING->value,
        'reason' => 'Course opening is pending',
        'changed_by_user_id' => $this->user->id,
    ]);

    actingAs($this->user)
        ->get(route('students.academic-summary.lifecycle', $this->student->id))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('student.status', 'pending_course_opening')
            ->where('options.action.allowedActionTypes', [
                StudentActionType::ACADEMIC_RESUME->value,
                StudentActionType::ACADEMIC_DEFER->value,
                StudentActionType::ACADEMIC_DROPOUT->value,
            ])
        );
});

it('withdraws Program Enrollment without mutating Student identity or account status', function () {
    $accountStatus = $this->student->user?->status;

    RecordStudentActionAction::run([
        'student_id' => $this->student->id,
        'action_type' => StudentActionType::ACADEMIC_DROPOUT->value,
        'reason' => 'Student withdrew',
        'dropout_semester_id' => $this->semester->id,
        'changed_by_user_id' => $this->user->id,
    ]);

    $enrollment = ProgramEnrollment::query()->sole();

    expect($enrollment->enrollment_status)->toBe('withdrawn')
        ->and($enrollment->study_stage)->toBe('intake_pre_uni_gc')
        ->and($this->student->fresh()->status)->toBe('intake_pre_uni_gc')
        ->and($this->student->fresh()->user?->status)->toBe($accountStatus);
});

it('rolls back the lifecycle transition and audit log when the Finance handoff fails', function () {
    app()->instance(StudentLifecycleFinanceCommand::class, new class implements StudentLifecycleFinanceCommand
    {
        public function applyDefer(StudentLifecycleDeferData $data): int
        {
            throw new RuntimeException('Simulated Finance failure');
        }
    });

    expect(fn () => RecordStudentActionAction::run([
        'student_id' => $this->student->id,
        'action_type' => StudentActionType::ACADEMIC_DEFER->value,
        'reason' => 'Defer with failed Finance handoff',
        'from_semester_id' => $this->semester->id,
        'return_semester_id' => $this->semester->id,
        'defer_scope_type' => 'FULL',
        'defer_fee_policy' => 'PRESERVE',
        'egc_defer_from_block_number' => 1,
        'changed_by_user_id' => $this->user->id,
    ]))->toThrow(RuntimeException::class, 'Simulated Finance failure');

    expect(ProgramEnrollment::query()->where('student_id', $this->student->id)->exists())->toBeFalse()
        ->and(StudentActionLog::query()->where('student_id', $this->student->id)->exists())->toBeFalse()
        ->and($this->student->fresh()->status)->toBe('intake_pre_uni_gc');
});

it('redirects the retired standalone actions and placement pages into the Lifecycle tab', function () {
    actingAs($this->user)
        ->get(route('students.actions.index', $this->student->id))
        ->assertRedirect(route('students.academic-summary.lifecycle', $this->student->id));

    actingAs($this->user)
        ->get(route('students.placement.index', $this->student->id))
        ->assertRedirect(route('students.academic-summary.lifecycle', $this->student->id));
});

it('backfills a decision onto a transition through the Lifecycle tab endpoint', function () {
    $action = StudentActionLog::query()->create([
        'student_id' => $this->student->id,
        'action_type' => StudentActionType::ACADEMIC_DROPOUT->value,
        'reason' => 'Backfill smoke fixture',
        'changed_by_user_id' => $this->user->id,
    ]);
    $decision = StudentDecision::query()->create([
        'decision_name' => 'Smoke decision',
        'decision_number' => 'QD-820-001',
        'decision_signer' => 'Academic Office',
        'issued_at' => '2026-05-01',
        'changed_by_user_id' => $this->user->id,
    ]);

    actingAs($this->user)
        ->withSession(['_token' => HUB_LIFECYCLE_CSRF, 'current_campus_id' => $this->campus->id])
        ->post(route('students.academic-summary.lifecycle.attach-decision', $this->student->id), [
            '_token' => HUB_LIFECYCLE_CSRF,
            'source' => 'action',
            'source_id' => $action->id,
            'decision_id' => $decision->id,
        ])
        ->assertRedirect();

    expect($action->fresh()->decision_id)->toBe($decision->id)
        ->and($decision->students()->whereKey($this->student->id)->exists())->toBeTrue();
});
