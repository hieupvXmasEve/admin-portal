<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentWallet;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/**
 * Issue 06 — the read-only Finance tab in the Student Hub.
 *
 * The Hub is finance-aware but not finance-owning (ADR-0007): the tab renders a
 * read-only fees + gold + scholarships summary sourced from the Finance read
 * contract, deep-links to the Finance Office, and exposes no mutation path.
 */
function financeTabStudent(): Student
{
    $campus = Campus::factory()->create();
    $program = Program::factory()->create();
    $semester = Semester::factory()->active()->create();

    $student = Student::factory()
        ->forCampus($campus)
        ->forProgram($program)
        ->create([
            'student_id' => 'FIN00001',
            'intake' => 1,
            'intake_mode' => 'sequential',
            'intake_semester_id' => $semester->id,
        ]);

    StudentWallet::query()->create(['student_id' => $student->id, 'balance' => 500]);

    return $student;
}

/**
 * Bind a user + campus session and mock the resolved permission set.
 */
function actAsFinanceTabUser(array $permissions): User
{
    $user = User::factory()->create();
    $campus = Campus::factory()->create();

    session(['current_campus_id' => $campus->id]);
    app()->singleton('campus', fn () => $campus);

    $permissionService = Mockery::mock(PermissionService::class);
    $permissionService->shouldReceive('getUserPermissions')->andReturn($permissions);
    app()->singleton(PermissionService::class, fn () => $permissionService);

    return $user;
}

it('renders the read-only Finance tab with summary and Finance Office deep link', function () {
    $student = financeTabStudent();
    $user = actAsFinanceTabUser(['view_student_summary']);

    actingAs($user)
        ->get(route('students.academic-summary.finance', $student))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Students/AcademicSummary/Finance')
            ->has('finance.fees')
            ->has('finance.gold')
            ->has('finance.scholarships')
            ->where('finance.gold.balance', 500)
            ->where('financeOfficeUrl', route('finance.students.overview', $student->id)));
});

it('forbids the Finance tab without view_student_summary', function () {
    $student = financeTabStudent();
    $user = actAsFinanceTabUser([]);

    actingAs($user)
        ->get(route('students.academic-summary.finance', $student))
        ->assertForbidden();
});

it('exposes no mutation path on the Hub Finance tab', function () {
    $student = financeTabStudent();
    $user = actAsFinanceTabUser(['view_student_summary', 'change_student_status']);

    // The finance tab is GET-only: a state-changing verb has no route to hit.
    actingAs($user)
        ->post(route('students.academic-summary.finance', $student))
        ->assertStatus(405);
});
