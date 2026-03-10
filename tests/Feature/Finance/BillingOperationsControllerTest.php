<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\CurriculumVersion;
use App\Models\FinanceCharge;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentInvoice;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('shows all invoice statuses and finance totals for a student row on the billing dashboard', function () {
    /** @var User $authorizedUser */
    $authorizedUser = User::factory()->create();
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->active()->create();
    $program = Program::factory()->create();
    $curriculumVersion = CurriculumVersion::factory()
        ->forProgram($program)
        ->withEffectiveSemester($semester)
        ->create();

    session(['current_campus_id' => $campus->id]);

    app()->singleton('campus', fn () => $campus);

    $permissionService = Mockery::mock(PermissionService::class);
    $permissionService->shouldReceive('getUserPermissions')
        ->andReturnUsing(fn ($user, $campusId) => $user->id === $authorizedUser->id ? ['view_finance_operations_dashboard'] : []);

    app()->singleton(PermissionService::class, fn () => $permissionService);

    $student = Student::factory()
        ->forCampus($campus)
        ->forProgram($program)
        ->state([
            'student_id' => 'AUH115952',
            'full_name' => 'Nguyen Manh Hung',
            'curriculum_version_id' => $curriculumVersion->id,
            'intake_semester_id' => $semester->id,
            'intake' => 1,
            'intake_mode' => 'sequential',
            'status' => 'intake_course',
            'intake_gc' => $semester->id,
            'intake_course' => $semester->id,
            'intake_major' => $semester->id,
        ])
        ->create();

    $tuitionCharge = FinanceCharge::create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
        'amount' => 75000000,
        'description' => 'Major tuition',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);

    FinanceCharge::create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'charge_type' => FinanceCharge::TYPE_SCHOLARSHIP_CREDIT,
        'amount' => -19500000,
        'description' => 'Scholarship credit',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);

    $payment = Payment::create([
        'student_id' => $student->id,
        'amount' => 30000000,
        'method' => Payment::METHOD_BANK_TRANSFER,
        'source' => 'test',
        'paid_at' => now(),
        'status' => Payment::STATUS_COMPLETED,
    ]);

    PaymentAllocation::create([
        'payment_id' => $payment->id,
        'charge_id' => $tuitionCharge->id,
        'allocated_amount' => 30000000,
        'allocated_at' => now(),
    ]);

    StudentInvoice::create([
        'invoice_number' => 'INV-001',
        'student_id' => $student->id,
        'billing_cycle_id' => null,
        'semester_id' => $semester->id,
        'status' => 'partial',
        'due_date' => now()->addDays(7),
    ]);

    StudentInvoice::create([
        'invoice_number' => 'INV-002',
        'student_id' => $student->id,
        'billing_cycle_id' => null,
        'semester_id' => $semester->id,
        'status' => 'pending',
        'due_date' => now()->addDays(14),
    ]);

    /** @var \Illuminate\Contracts\Auth\Authenticatable $authenticatedUser */
    $authenticatedUser = $authorizedUser;

    $response = actingAs($authenticatedUser)
        ->get(route('finance.operations.dashboard', [
            'semester_id' => $semester->id,
            'search' => 'AUH115952',
            'sort' => 'full_name',
        ]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Finance/Operations/Dashboard')
        ->has('students.data', 1)
        ->where('students.data.0.student_id', 'AUH115952')
        ->where('students.data.0.total_charged', 75000000)
        ->where('students.data.0.total_credits', 19500000)
        ->where('students.data.0.total_paid', 30000000)
        ->where('students.data.0.balance', 25500000)
        ->where('students.data.0.amount_due', 25500000)
        ->has('students.data.0.invoices', 2)
        ->where('students.data.0.invoices.0.invoice_number', 'INV-002')
        ->where('students.data.0.invoices.0.status', 'pending')
        ->where('students.data.0.invoices.1.invoice_number', 'INV-001')
        ->where('students.data.0.invoices.1.status', 'partial')
    );
});

it('keeps a student row when any semester invoice matches the selected status filter', function () {
    /** @var User $authorizedUser */
    $authorizedUser = User::factory()->create();
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->active()->create();
    $program = Program::factory()->create();
    $curriculumVersion = CurriculumVersion::factory()
        ->forProgram($program)
        ->withEffectiveSemester($semester)
        ->create();

    session(['current_campus_id' => $campus->id]);

    app()->singleton('campus', fn () => $campus);

    $permissionService = Mockery::mock(PermissionService::class);
    $permissionService->shouldReceive('getUserPermissions')
        ->andReturnUsing(fn ($user, $campusId) => $user->id === $authorizedUser->id ? ['view_finance_operations_dashboard'] : []);

    app()->singleton(PermissionService::class, fn () => $permissionService);

    $student = Student::factory()
        ->forCampus($campus)
        ->forProgram($program)
        ->state([
            'student_id' => 'AUH115952',
            'full_name' => 'Nguyen Manh Hung',
            'curriculum_version_id' => $curriculumVersion->id,
            'intake_semester_id' => $semester->id,
            'intake' => 1,
            'intake_mode' => 'sequential',
            'status' => 'intake_course',
            'intake_gc' => $semester->id,
            'intake_course' => $semester->id,
            'intake_major' => $semester->id,
        ])
        ->create();

    FinanceCharge::create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
        'amount' => 1000000,
        'description' => 'Major tuition',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);

    StudentInvoice::create([
        'invoice_number' => 'INV-001',
        'student_id' => $student->id,
        'billing_cycle_id' => null,
        'semester_id' => $semester->id,
        'status' => 'partial',
        'due_date' => now()->addDays(7),
    ]);

    StudentInvoice::create([
        'invoice_number' => 'INV-002',
        'student_id' => $student->id,
        'billing_cycle_id' => null,
        'semester_id' => $semester->id,
        'status' => 'pending',
        'due_date' => now()->addDays(14),
    ]);

    /** @var \Illuminate\Contracts\Auth\Authenticatable $authenticatedUser */
    $authenticatedUser = $authorizedUser;

    $response = actingAs($authenticatedUser)
        ->get(route('finance.operations.dashboard', [
            'semester_id' => $semester->id,
            'status' => 'partial',
            'search' => 'AUH115952',
        ]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Finance/Operations/Dashboard')
        ->has('students.data', 1)
        ->where('students.data.0.student_id', 'AUH115952')
        ->has('students.data.0.invoices', 2)
    );
});
