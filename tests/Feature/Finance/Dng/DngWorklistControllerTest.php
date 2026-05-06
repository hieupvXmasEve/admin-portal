<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\CurriculumVersion;
use App\Models\FinanceCharge;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Finance\Actions\CreateBatchDngFromChargesAction;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

// ─── Helpers ────────────────────────────────────────────────────────────────

function makeControllerStudent(object $ctx, string $code): Student
{
    return Student::factory()
        ->forCampus($ctx->campus)
        ->forProgram($ctx->program)
        ->state([
            'student_id'            => $code,
            'full_name'             => "Student {$code}",
            'email'                 => strtolower($code) . '@test.com',
            'curriculum_version_id' => $ctx->cv->id,
            'intake_semester_id'    => $ctx->semester->id,
            'intake'                => 1,
            'intake_mode'           => 'sequential',
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
    $this->user     = User::factory()->create();
    $this->campus   = Campus::factory()->create(['dng_code' => 'FAUHN', 'code' => 'FAU']);
    $this->semester = Semester::factory()->active()->create();
    $this->program  = Program::factory()->create();
    $this->cv       = CurriculumVersion::factory()
        ->forProgram($this->program)
        ->withEffectiveSemester($this->semester)
        ->create();

    session(['current_campus_id' => $this->campus->id]);
    app()->singleton('campus', fn () => $this->campus);

    mockPermissions(['create_finance_payments', 'view_finance_operations']);
});

it('GET /finance/operations/dng-worklist renders Inertia page with required props', function () {
    actingAs($this->user);

    $response = get(route('finance.operations.dng-worklist'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Finance/Operations/DngWorklist')
            ->has('students')
            ->has('filters')
            ->has('summary')
            ->has('feeTypeOptions')
            ->has('semesters')
            ->has('campuses')
        );
});

it('GET with dng_fee_type=HL returns HL charges in response', function () {
    actingAs($this->user);

    $student = makeControllerStudent($this, 'HL-CTL');
    FinanceCharge::create([
        'student_id'   => $student->id,
        'semester_id'  => $this->semester->id,
        'charge_type'  => FinanceCharge::TYPE_RETAKE_FEE,
        'amount'       => 1_500_000,
        'description'  => 'Retake fee',
        'effective_at' => now(),
        'status'       => FinanceCharge::STATUS_ACTIVE,
    ]);

    $response = get(route('finance.operations.dng-worklist', ['dng_fee_type' => 'HL']));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Finance/Operations/DngWorklist')
            ->where('filters.dng_fee_type', 'HL')
            ->has('students.data', 1)
        );
});

it('GET requires authentication', function () {
    get(route('finance.operations.dng-worklist'))
        ->assertRedirect(route('login'));
});

it('GET requires create_finance_payments permission', function () {
    mockPermissions([]); // no permissions

    actingAs($this->user);

    get(route('finance.operations.dng-worklist'))
        ->assertForbidden();
});

it('POST /finance/operations/dng-worklist creates DNG and redirects back', function () {
    $student = makeControllerStudent($this, 'POST001');
    FinanceCharge::create([
        'student_id'   => $student->id,
        'semester_id'  => $this->semester->id,
        'charge_type'  => FinanceCharge::TYPE_TUITION_TERM,
        'amount'       => 10_000_000,
        'description'  => 'Tuition',
        'effective_at' => now(),
        'status'       => FinanceCharge::STATUS_ACTIVE,
    ]);

    $mockAction = Mockery::mock(CreateBatchDngFromChargesAction::class);
    $mockAction->shouldReceive('handle')
        ->once()
        ->andReturn(['created' => 1, 'failed' => 0, 'cancelled_old' => 0, 'errors' => []]);

    app()->instance(CreateBatchDngFromChargesAction::class, $mockAction);

    $response = actingAs($this->user)
        ->withSession(['_token' => 'test-token'])
        ->post(route('finance.operations.dng-worklist.store'), [
            '_token'        => 'test-token',
            'student_ids'   => [$student->id],
            'dng_fee_type'  => 'HP',
            'due_date'      => now()->addDays(30)->toDateString(),
            'semester_id'   => $this->semester->id,
            'description'   => 'Test DNG',
            'estimate_time' => '05/26',
        ]);

    $response->assertRedirect();
});

it('POST validates required fields', function () {
    $response = actingAs($this->user)
        ->withSession(['_token' => 'test-token'])
        ->post(route('finance.operations.dng-worklist.store'), [
            '_token' => 'test-token',
        ]);

    $response->assertSessionHasErrors([
        'student_ids',
        'dng_fee_type',
        'due_date',
        'semester_id',
        'description',
        'estimate_time',
    ]);
});

it('POST requires authentication', function () {
    $this->withSession(['_token' => 'test-token'])
        ->post(route('finance.operations.dng-worklist.store'), ['_token' => 'test-token'])
        ->assertRedirect(route('login'));
});

it('POST validates student_ids max 100', function () {
    $tooMany = range(1, 101);

    $response = actingAs($this->user)
        ->withSession(['_token' => 'test-token'])
        ->post(route('finance.operations.dng-worklist.store'), [
            '_token'        => 'test-token',
            'student_ids'   => $tooMany,
            'dng_fee_type'  => 'HP',
            'due_date'      => now()->addDays(30)->toDateString(),
            'semester_id'   => $this->semester->id,
            'description'   => 'Too many students',
            'estimate_time' => '05/26',
        ]);

    $response->assertSessionHasErrors(['student_ids']);
});

it('POST validates due_date is not in the past', function () {
    $student = makeControllerStudent($this, 'PAST001');

    $response = actingAs($this->user)
        ->withSession(['_token' => 'test-token'])
        ->post(route('finance.operations.dng-worklist.store'), [
            '_token'        => 'test-token',
            'student_ids'   => [$student->id],
            'dng_fee_type'  => 'HP',
            'due_date'      => now()->subDays(1)->toDateString(),
            'semester_id'   => $this->semester->id,
            'description'   => 'Past due date',
            'estimate_time' => '05/26',
        ]);

    $response->assertSessionHasErrors(['due_date']);
});

it('GET defaults to HP fee_type when no param provided', function () {
    actingAs($this->user);

    $response = get(route('finance.operations.dng-worklist'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.dng_fee_type', 'HP')
        );
});

it('GET summary shows correct counts', function () {
    actingAs($this->user);

    $s1 = makeControllerStudent($this, 'SUM-CTL-1');
    $s2 = makeControllerStudent($this, 'SUM-CTL-2');

    FinanceCharge::create([
        'student_id'   => $s1->id,
        'semester_id'  => $this->semester->id,
        'charge_type'  => FinanceCharge::TYPE_TUITION_TERM,
        'amount'       => 5_000_000,
        'description'  => 'Charge 1',
        'effective_at' => now(),
        'status'       => FinanceCharge::STATUS_ACTIVE,
    ]);

    FinanceCharge::create([
        'student_id'   => $s2->id,
        'semester_id'  => $this->semester->id,
        'charge_type'  => FinanceCharge::TYPE_TUITION_TERM,
        'amount'       => 3_000_000,
        'description'  => 'Charge 2',
        'effective_at' => now(),
        'status'       => FinanceCharge::STATUS_ACTIVE,
    ]);

    $response = get(route('finance.operations.dng-worklist', ['dng_fee_type' => 'HP']));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('summary.total_students', 2)
            ->where('summary.students_without_dng', 2)
        );
});
