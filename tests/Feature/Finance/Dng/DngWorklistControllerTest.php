<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\CurriculumVersion;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Finance\Actions\CreateBatchDngFromChargesAction;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

// ─── Helpers ────────────────────────────────────────────────────────────────

function makeControllerStudent(object $ctx, string $code): Student
{
    return Student::factory()
        ->forCampus($ctx->campus)
        ->forProgram($ctx->program)
        ->state([
            'student_id' => $code,
            'full_name' => "Student {$code}",
            'email' => strtolower($code).'@test.com',
            'curriculum_version_id' => $ctx->cv->id,
            'intake_semester_id' => $ctx->semester->id,
            'intake' => 1,
            'intake_mode' => 'sequential',
        ])
        ->create();
}

function mockPermissions(array $permissions): void
{
    $permissionService = Mockery::mock(PermissionService::class);
    $permissionService->shouldReceive('getUserPermissions')->andReturn($permissions);
    app()->singleton(PermissionService::class, fn () => $permissionService);
}

// ─── Tests ──────────────────────────────────────────────────────────────────

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->campus = Campus::factory()->create(['dng_code' => 'FAUHN', 'code' => 'FAU']);
    $this->semester = Semester::factory()->active()->create();
    $this->program = Program::factory()->create();
    $this->cv = CurriculumVersion::factory()
        ->forProgram($this->program)
        ->withEffectiveSemester($this->semester)
        ->create();

    session(['current_campus_id' => $this->campus->id]);
    app()->singleton('campus', fn () => $this->campus);

    mockPermissions([
        'view_finance_batch_studio',
        'create_finance_payments',
        'void_finance_charges',
    ]);
});

it('redirects the old DNG worklist URL to the Batch Studio DNG wizard', function () {
    actingAs($this->user);

    $student = makeControllerStudent($this, 'REDIR001');

    get(route('finance.operations.dng-worklist', [
        'dng_fee_type' => 'HL',
        'semester_id' => $this->semester->id,
        'student_ids' => [$student->id],
        'search' => 'ignored legacy table filter',
    ]))->assertRedirect(route('finance.batch-studio.dng', [
        'dng_fee_type' => 'HL',
        'semester_id' => $this->semester->id,
        'student_ids' => [$student->id],
    ]));
});

it('GET requires authentication', function () {
    get(route('finance.operations.dng-worklist'))
        ->assertRedirect(route('login'));
});

it('GET requires Batch Studio view permission', function () {
    mockPermissions([]); // no permissions

    actingAs($this->user);

    get(route('finance.operations.dng-worklist'))
        ->assertForbidden();
});

it('POST /finance/operations/dng-worklist is not an alternate DNG write path', function () {
    $student = makeControllerStudent($this, 'POST001');

    $mockAction = Mockery::mock(CreateBatchDngFromChargesAction::class);
    $mockAction->shouldReceive('handle')->never();

    app()->instance(CreateBatchDngFromChargesAction::class, $mockAction);

    $response = actingAs($this->user)
        ->withSession(['_token' => 'test-token'])
        ->post(route('finance.operations.dng-worklist.store'), [
            '_token' => 'test-token',
            'student_ids' => [$student->id],
            'dng_fee_type' => 'HP',
            'due_date' => now()->addDays(30)->toDateString(),
            'semester_id' => $this->semester->id,
            'description' => 'Test DNG',
            'estimate_time' => '05/26',
        ]);

    $response->assertGone();
});

it('POST requires authentication', function () {
    $this->withSession(['_token' => 'test-token'])
        ->post(route('finance.operations.dng-worklist.store'), ['_token' => 'test-token'])
        ->assertRedirect(route('login'));
});

it('Batch Studio DNG wizard exposes sanitized legacy prefill props', function () {
    actingAs($this->user);

    $student = makeControllerStudent($this, 'PREF001');

    $response = get(route('finance.batch-studio.dng', [
        'dng_fee_type' => 'HL',
        'semester_id' => $this->semester->id,
        'student_ids' => [$student->id],
        'search' => 'ignored legacy table filter',
    ]));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Finance/BatchStudio/DngPush')
            ->where('prefill.dng_fee_type', 'HL')
            ->where('prefill.semester_id', $this->semester->id)
            ->where('prefill.student_ids.0', $student->id)
        );
});
