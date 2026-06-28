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
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
