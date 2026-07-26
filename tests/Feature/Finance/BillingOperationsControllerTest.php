<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\CurriculumVersion;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Finance\Models\DiscountAllocation;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\InvoiceDiscount;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\PaymentApplication;
use App\Modules\Finance\Models\StudentInvoice;
use App\Shared\Contracts\Identity\CampusPermissionReader;
use Illuminate\Contracts\Auth\Authenticatable;
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

    $permissionService = Mockery::mock(CampusPermissionReader::class);
    $permissionService->shouldReceive('permissionCodesForUserId')
        ->andReturnUsing(fn ($user, $campusId) => $user->id === $authorizedUser->id ? ['view_finance_operations_dashboard'] : []);

    app()->singleton(CampusPermissionReader::class, fn () => $permissionService);

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

    $payment = Payment::create([
        'student_id' => $student->id,
        'amount' => 30000000,
        'method' => Payment::METHOD_BANK_TRANSFER,
        'source' => 'test',
        'paid_at' => now(),
        'status' => Payment::STATUS_COMPLETED,
    ]);

    $invoiceOne = StudentInvoice::create([
        'invoice_number' => 'INV-001',
        'student_id' => $student->id,
        'billing_cycle_id' => null,
        'semester_id' => $semester->id,
        'status' => 'partial',
        'due_date' => now()->addDays(7),
        'subtotal' => 75000000,
        'discount_total' => 19500000,
        'total_amount' => 55500000,
        'paid_amount' => 30000000,
    ]);

    StudentInvoice::create([
        'invoice_number' => 'INV-002',
        'student_id' => $student->id,
        'billing_cycle_id' => null,
        'semester_id' => $semester->id,
        'status' => 'pending',
        'due_date' => now()->addDays(14),
        'subtotal' => 0,
        'discount_total' => 0,
        'total_amount' => 0,
        'paid_amount' => 0,
    ]);

    $line = InvoiceLine::create([
        'invoice_id' => $invoiceOne->id,
        'charge_id' => $tuitionCharge->id,
        'amount_snapshot' => 75000000,
        'description_snapshot' => 'Major tuition',
        'status' => 'active',
    ]);

    PaymentApplication::create([
        'payment_id' => $payment->id,
        'invoice_line_id' => $line->id,
        'amount' => 30000000,
        'entry_type' => 'application',
        'applied_at' => now(),
    ]);

    InvoiceDiscount::create([
        'invoice_id' => $invoiceOne->id,
        'discount_type' => 'scholarship',
        'discount_source' => 'App\\Models\\StudentScholarshipAward',
        'description' => 'Scholarship credit',
        'amount' => 19500000,
        'reference_id' => 1,
    ]);

    $discount = InvoiceDiscount::query()->firstOrFail();

    DiscountAllocation::create([
        'invoice_discount_id' => $discount->id,
        'invoice_line_id' => $line->id,
        'amount' => 19500000,
        'entry_type' => 'allocation',
        'allocation_rule' => 'oldest_line_first',
    ]);

    $invoiceOne->forceFill([
        'subtotal' => 0,
        'discount_total' => 0,
        'total_amount' => 0,
        'paid_amount' => 0,
    ])->save();

    /** @var Authenticatable $authenticatedUser */
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
        ->where('students.data.0.gross_billed', 75000000)
        ->where('students.data.0.total_discounts', 19500000)
        ->where('students.data.0.cash_applied', 30000000)
        ->where('students.data.0.net_due', 55500000)
        ->where('students.data.0.outstanding_amount', 25500000)
        ->has('students.data.0.invoices', 2)
        ->where('students.data.0.invoices.0.invoice_number', 'INV-002')
        ->where('students.data.0.invoices.0.status', 'paid')
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

    $permissionService = Mockery::mock(CampusPermissionReader::class);
    $permissionService->shouldReceive('permissionCodesForUserId')
        ->andReturnUsing(fn ($user, $campusId) => $user->id === $authorizedUser->id ? ['view_finance_operations_dashboard'] : []);

    app()->singleton(CampusPermissionReader::class, fn () => $permissionService);

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

    $charge = FinanceCharge::query()->firstOrFail();

    $invoiceOne = StudentInvoice::create([
        'invoice_number' => 'INV-001',
        'student_id' => $student->id,
        'billing_cycle_id' => null,
        'semester_id' => $semester->id,
        'status' => 'partial',
        'due_date' => now()->addDays(7),
        'subtotal' => 1000000,
        'discount_total' => 0,
        'total_amount' => 1000000,
        'paid_amount' => 500000,
    ]);

    StudentInvoice::create([
        'invoice_number' => 'INV-002',
        'student_id' => $student->id,
        'billing_cycle_id' => null,
        'semester_id' => $semester->id,
        'status' => 'pending',
        'due_date' => now()->addDays(14),
        'subtotal' => 1000000,
        'discount_total' => 0,
        'total_amount' => 1000000,
        'paid_amount' => 0,
    ]);

    $line = InvoiceLine::create([
        'invoice_id' => $invoiceOne->id,
        'charge_id' => $charge->id,
        'amount_snapshot' => 1000000,
        'description_snapshot' => 'Major tuition',
        'status' => 'active',
    ]);

    Payment::create([
        'student_id' => $student->id,
        'amount' => 500000,
        'method' => Payment::METHOD_BANK_TRANSFER,
        'source' => 'test',
        'paid_at' => now(),
        'status' => Payment::STATUS_COMPLETED,
    ]);

    $payment = Payment::query()->firstOrFail();

    PaymentApplication::create([
        'payment_id' => $payment->id,
        'invoice_line_id' => $line->id,
        'amount' => 500000,
        'entry_type' => 'application',
        'applied_at' => now(),
    ]);

    /** @var Authenticatable $authenticatedUser */
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

it('sorts dashboard students globally by gross billed before pagination', function () {
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

    $permissionService = Mockery::mock(CampusPermissionReader::class);
    $permissionService->shouldReceive('permissionCodesForUserId')
        ->andReturnUsing(fn ($user, $campusId) => $user->id === $authorizedUser->id ? ['view_finance_operations_dashboard'] : []);
    app()->singleton(CampusPermissionReader::class, fn () => $permissionService);

    $studentA = Student::factory()->forCampus($campus)->forProgram($program)->state([
        'student_id' => 'AUH-A',
        'full_name' => 'Student A',
        'curriculum_version_id' => $curriculumVersion->id,
        'intake_semester_id' => $semester->id,
        'intake' => 1,
        'intake_mode' => 'sequential',
        'status' => 'intake_course',
    ])->create();

    $studentB = Student::factory()->forCampus($campus)->forProgram($program)->state([
        'student_id' => 'AUH-B',
        'full_name' => 'Student B',
        'curriculum_version_id' => $curriculumVersion->id,
        'intake_semester_id' => $semester->id,
        'intake' => 1,
        'intake_mode' => 'sequential',
        'status' => 'intake_course',
    ])->create();

    $invoiceA = StudentInvoice::create([
        'invoice_number' => 'INV-A',
        'student_id' => $studentA->id,
        'billing_cycle_id' => null,
        'semester_id' => $semester->id,
        'status' => 'pending',
        'due_date' => now()->addDays(7),
        'subtotal' => 1000000,
        'discount_total' => 0,
        'total_amount' => 1000000,
        'paid_amount' => 0,
    ]);

    $invoiceB = StudentInvoice::create([
        'invoice_number' => 'INV-B',
        'student_id' => $studentB->id,
        'billing_cycle_id' => null,
        'semester_id' => $semester->id,
        'status' => 'pending',
        'due_date' => now()->addDays(7),
        'subtotal' => 9000000,
        'discount_total' => 0,
        'total_amount' => 9000000,
        'paid_amount' => 0,
    ]);

    $chargeA = FinanceCharge::create([
        'student_id' => $studentA->id,
        'semester_id' => $semester->id,
        'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
        'amount' => 1000000,
        'description' => 'A tuition',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);

    $chargeB = FinanceCharge::create([
        'student_id' => $studentB->id,
        'semester_id' => $semester->id,
        'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
        'amount' => 9000000,
        'description' => 'B tuition',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);

    InvoiceLine::create([
        'invoice_id' => $invoiceA->id,
        'charge_id' => $chargeA->id,
        'amount_snapshot' => 1000000,
        'description_snapshot' => 'A tuition',
        'status' => 'active',
    ]);

    InvoiceLine::create([
        'invoice_id' => $invoiceB->id,
        'charge_id' => $chargeB->id,
        'amount_snapshot' => 9000000,
        'description_snapshot' => 'B tuition',
        'status' => 'active',
    ]);

    /** @var \\Illuminate\\Contracts\\Auth\\Authenticatable $authenticatedUser */
    $authenticatedUser = $authorizedUser;

    $response = actingAs($authenticatedUser)
        ->get(route('finance.operations.dashboard', [
            'semester_id' => $semester->id,
            'sort' => 'gross_billed',
            'direction' => 'desc',
            'per_page' => 1,
            'page' => 1,
        ]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Finance/Operations/Dashboard')
        ->has('students.data', 1)
        ->where('students.data.0.student_id', 'AUH-B')
        ->where('students.data.0.gross_billed', 9000000)
    );
});
