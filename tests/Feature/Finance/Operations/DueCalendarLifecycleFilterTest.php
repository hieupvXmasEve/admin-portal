<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Queries\Operations\GetDueItemsSummaryQuery;
use App\Modules\Finance\Queries\Operations\ListDueItemsQuery;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

function makeLifecycleFinanceStudent(Campus $campus, Program $program, Semester $semester, string $code, string $status): Student
{
    return Student::factory()
        ->forCampus($campus)
        ->forProgram($program)
        ->state([
            'student_id' => $code,
            'full_name' => "Student {$code}",
            'email' => strtolower($code).'@example.com',
            'intake' => 1,
            'intake_mode' => 'sequential',
            'intake_semester_id' => $semester->id,
            'status' => $status,
            'intake_gc' => $semester->id,
            'intake_course' => $semester->id,
            'intake_major' => $semester->id,
        ])
        ->create();
}

function makeLifecycleDngRequest(Student $student, Semester $semester, string $suffix = '1'): DngPaymentRequest
{
    return DngPaymentRequest::create([
        'student_id' => $student->id,
        'campus_code' => 'TEST',
        'student_code' => $student->student_id,
        'item_id' => "ITEM-{$suffix}",
        'fee_type' => 'tuition',
        'description' => 'Tuition',
        'semester_id' => $semester->id,
        'due_date' => now()->subDay(),
        'amount' => 1500000,
        'status' => 'pushed_to_dng',
    ]);
}

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->campus = Campus::factory()->create();
    $this->semester = Semester::factory()->active()->create();
    $this->program = Program::factory()->create();

    session(['current_campus_id' => $this->campus->id]);
    app()->singleton('campus', fn () => $this->campus);

    $permissionService = Mockery::mock(PermissionService::class);
    $permissionService->shouldReceive('getUserPermissions')->andReturn(['view_finance_operations_due_calendar']);
    app()->singleton(PermissionService::class, fn () => $permissionService);
});

it('excludes lifecycle exceptions from due calendar list and summary', function () {
    $activeStudent = makeLifecycleFinanceStudent($this->campus, $this->program, $this->semester, 'ACTIVE001', 'intake_course');
    $deferredStudent = makeLifecycleFinanceStudent($this->campus, $this->program, $this->semester, 'DEF001', 'deferred');

    $activeRequest = makeLifecycleDngRequest($activeStudent, $this->semester, 'active');
    makeLifecycleDngRequest($deferredStudent, $this->semester, 'deferred');

    $list = app(ListDueItemsQuery::class)->handle($this->semester->id, 'overdue', null);
    $summary = app(GetDueItemsSummaryQuery::class)->handle($this->semester->id);

    expect($list->total())->toBe(1)
        ->and($list->items()[0]['id'])->toBe($activeRequest->id)
        ->and($summary['overdue_count'])->toBe(1)
        ->and($summary['total_overdue_amount'])->toBe(1500000.0);
});

it('renders lifecycle exceptions page with excluded DNG requests', function () {
    $deferredStudent = makeLifecycleFinanceStudent($this->campus, $this->program, $this->semester, 'DEF002', 'deferred');
    $exceptionRequest = makeLifecycleDngRequest($deferredStudent, $this->semester, 'deferred-page');

    actingAs($this->user);

    get(route('finance.operations.lifecycle-exceptions'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Finance/Operations/LifecycleExceptions')
            ->has('exceptions')
            ->has('summary')
            ->where('exceptions.data.0.id', $exceptionRequest->id)
            ->where('summary.total_count', 1));
});
