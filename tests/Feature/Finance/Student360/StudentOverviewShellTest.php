<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

if (! function_exists('grantFinanceOverview')) {
    function grantFinanceOverview(array $codes): User
    {
        $user = User::factory()->create();
        $mock = Mockery::mock(PermissionService::class);
        $mock->shouldReceive('getUserPermissions')->andReturn($codes);
        app()->singleton(PermissionService::class, fn () => $mock);

        return $user;
    }
}

beforeEach(function () {
    $this->campus = Campus::factory()->create();
    $this->otherCampus = Campus::factory()->create();
    $this->program = Program::factory()->create();
    $this->semester = Semester::factory()->create();

    session(['current_campus_id' => $this->campus->id]);
    app()->singleton('campus', fn () => $this->campus);
});

function makeOverviewStudent(Campus $campus, Program $program, Semester $semester): Student
{
    return Student::factory()->forCampus($campus)->forProgram($program)
        ->state(['intake' => 1, 'intake_semester_id' => $semester->id])
        ->create();
}

it('renders the 360 shell with identity and four balances for a visible student', function () {
    $user = grantFinanceOverview(['view_finance_student_overview']);
    $student = makeOverviewStudent($this->campus, $this->program, $this->semester);

    actingAs($user)->get("/finance/students/{$student->id}")
        ->assertInertia(fn ($page) => $page
            ->component('Finance/Student360/Show')
            ->where('student.id', $student->id)
            ->where('student.student_code', $student->student_id)
            ->has('student.lifecycle_reason')
            ->has('balances.net_charges')
            ->has('balances.total_paid')
            ->has('balances.balance')
            ->has('balances.unapplied_credit')
            ->where('focus', null));
});

it('echoes a valid focus target', function () {
    $user = grantFinanceOverview(['view_finance_student_overview']);
    $student = makeOverviewStudent($this->campus, $this->program, $this->semester);

    actingAs($user)->get("/finance/students/{$student->id}?focus=dng:7781")
        ->assertInertia(fn ($page) => $page
            ->where('focus.type', 'dng')
            ->where('focus.id', 7781));
});

it('denies access without the permission', function () {
    $user = grantFinanceOverview([]);
    $student = makeOverviewStudent($this->campus, $this->program, $this->semester);

    actingAs($user)->get("/finance/students/{$student->id}")->assertForbidden();
});

it('hides a cross-campus student as not found', function () {
    $user = grantFinanceOverview(['view_finance_student_overview']);
    $student = makeOverviewStudent($this->otherCampus, $this->program, $this->semester);

    actingAs($user)->get("/finance/students/{$student->id}")->assertNotFound();
});

it('allows cross-campus view with the all-campus permission', function () {
    $user = grantFinanceOverview(['view_finance_student_overview', 'view_finance_all_campus']);
    $student = makeOverviewStudent($this->otherCampus, $this->program, $this->semester);

    actingAs($user)->get("/finance/students/{$student->id}")
        ->assertInertia(fn ($page) => $page->component('Finance/Student360/Show'));
});
