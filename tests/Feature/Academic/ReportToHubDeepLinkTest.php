<?php

declare(strict_types=1);

use App\Enums\StudentActionType;
use App\Models\Campus;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Academic\Progression\Actions\RecordStudentActionAction;
use App\Modules\Academic\Queries\Reporting\GetStudentStatusBySemesterQuery;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/**
 * Issue 07 — the management Reports area deep-links one-way into the Student Hub.
 *
 * Locks the contract behind every "report row -> Hub tab" link:
 *  - the Hub tab routes resolve to the right student and the right tab, and
 *  - the report queries expose the student primary key the FE needs to build them, and
 *  - the deep-link destinations (Lifecycle, Scores & GPA) are real, reachable pages.
 *
 * Mapping under test (PRD / ADR-0007):
 *  - status / progression / decision audits  -> Lifecycle tab
 *  - grade analytics (performance, ranking, report) -> Scores & GPA tab
 */
beforeEach(function () {
    $this->user = User::factory()->create();
    $this->campus = Campus::factory()->create();
    $this->program = Program::factory()->create();
    $this->semester = Semester::factory()->create();
    $this->student = Student::factory()
        ->forCampus($this->campus)
        ->forProgram($this->program)
        ->create([
            'student_id' => 'SE700001',
            'status' => 'intake_course',
            'intake' => 1,
            'intake_semester_id' => $this->semester->id,
        ]);
});

function grantHubViewPermissions(array $permissions): void
{
    $permissionService = Mockery::mock(PermissionService::class);
    $permissionService->shouldReceive('getUserPermissions')->andReturn($permissions);
    app()->singleton(PermissionService::class, fn () => $permissionService);
}

it('resolves status / progression / decision report rows to the student Hub Lifecycle tab', function () {
    expect(route('students.academic-summary.lifecycle', $this->student->id, false))
        ->toBe("/students/{$this->student->id}/academic-summary/lifecycle");
});

it('resolves grade-analytics report rows to the student Hub Scores & GPA tab', function () {
    expect(route('students.academic-summary.scores', $this->student->id, false))
        ->toBe("/students/{$this->student->id}/academic-summary/scores");
});

it('targets each report row at the correct student instance, not a fixed id', function () {
    $other = Student::factory()
        ->forCampus($this->campus)
        ->forProgram($this->program)
        ->create([
            'student_id' => 'SE700002',
            'status' => 'intake_course',
            'intake' => 1,
            'intake_semester_id' => $this->semester->id,
        ]);

    expect(route('students.academic-summary.lifecycle', $this->student->id, false))
        ->not->toBe(route('students.academic-summary.lifecycle', $other->id, false))
        ->and(route('students.academic-summary.lifecycle', $other->id, false))
        ->toBe("/students/{$other->id}/academic-summary/lifecycle");
});

it('exposes the student primary key in the lifecycle status report so rows can deep-link', function () {
    $rows = (new GetStudentStatusBySemesterQuery)->handle($this->semester->id, $this->campus->id);

    expect($rows->total())->toBe(1)
        ->and($rows->items()[0]['id'])->toBe($this->student->id)
        ->and($rows->items()[0]['student_id'])->toBe('SE700001');
});

it('reports and filters the current status from Program Enrollment', function (): void {
    RecordStudentActionAction::run([
        'student_id' => $this->student->id,
        'action_type' => StudentActionType::ACADEMIC_DROPOUT->value,
        'reason' => 'Approved withdrawal',
        'dropout_semester_id' => $this->semester->id,
        'changed_by_user_id' => $this->user->id,
    ]);

    $rows = (new GetStudentStatusBySemesterQuery)->handle(
        $this->semester->id,
        $this->campus->id,
        'dropout',
    );

    expect($rows->total())->toBe(1)
        ->and($rows->items()[0]['current_status'])->toBe('dropout')
        ->and($this->student->fresh()->status)->toBe('intake_course');
});

it('reaches the Hub Lifecycle tab that the lifecycle reports link to', function () {
    grantHubViewPermissions(['view_student', 'view_student_summary', 'view_student_action']);

    actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->get(route('students.academic-summary.lifecycle', $this->student->id))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('students/AcademicSummary/Lifecycle')
            ->where('student.id', $this->student->id)
        );
});

it('reaches the Hub Scores & GPA tab that the grade reports link to', function () {
    grantHubViewPermissions(['view_student', 'view_student_summary', 'view_grade']);

    actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->get(route('students.academic-summary.scores', $this->student->id))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('students/AcademicSummary/Scores')
            ->where('student.id', $this->student->id)
        );
});
