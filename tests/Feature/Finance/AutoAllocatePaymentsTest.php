<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\CurriculumVersion;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\StudentInvoice;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->campus = Campus::factory()->create();
    $this->semester = Semester::factory()->active()->create();
    $this->program = Program::factory()->create();
    $this->curriculumVersion = CurriculumVersion::factory()
        ->forProgram($this->program)
        ->withEffectiveSemester($this->semester)
        ->create();

    session(['current_campus_id' => $this->campus->id]);
    app()->singleton('campus', fn () => $this->campus);

    $permissionService = Mockery::mock(PermissionService::class);
    $permissionService->shouldReceive('getUserPermissions')
        ->andReturn(['allocate_finance_payment', 'view_finance_payments']);

    app()->singleton(PermissionService::class, fn () => $permissionService);
});

it('shows the auto-allocate page', function () {
    $response = $this->actingAs($this->user)
        ->get(route('finance.payments.auto-allocate.show'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Finance/Payments/AutoAllocate')
    );
});

it('returns preview data for auto-allocation', function () {
    $student = Student::factory()
        ->forCampus($this->campus)
        ->forProgram($this->program)
        ->state([
            'curriculum_version_id' => $this->curriculumVersion->id,
            'intake_semester_id' => $this->semester->id,
            'intake' => 1,
            'intake_mode' => 'sequential',
        ])
        ->create();

    $charge = FinanceCharge::create([
        'student_id' => $student->id,
        'semester_id' => $this->semester->id,
        'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
        'amount' => 10000000,
        'description' => 'Tuition test',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);

    // Create unpaid invoice and link charge to it
    $invoice = StudentInvoice::create([
        'student_id' => $student->id,
        'semester_id' => $this->semester->id,
        'invoice_number' => 'INV-TEST-001',
        'status' => 'pending',
        'due_date' => now()->addDays(30),
    ]);

    InvoiceLine::create([
        'invoice_id' => $invoice->id,
        'charge_id' => $charge->id,
        'amount_snapshot' => $charge->amount,
        'description_snapshot' => $charge->description,
    ]);

    Payment::create([
        'student_id' => $student->id,
        'amount' => 5000000,
        'status' => Payment::STATUS_COMPLETED,
        'paid_at' => now(),
        'source' => 'manual',
    ]);

    $response = $this->actingAs($this->user)
        ->postJson(route('finance.payments.auto-allocate.preview'), [
            'priority_order' => ['tuition_term', 'egc_level_fee', 'retake_fee', 'manual_fee'],
        ]);

    $response->assertOk();
    $response->assertJsonStructure([
        'students' => [
            '*' => [
                'student_id',
                'student_code',
                'student_name',
                'payments',
                'allocations',
                'total_to_allocate',
            ],
        ],
        'summary' => [
            'total_students',
            'total_payments_affected',
            'total_allocations',
            'total_amount',
            'invoices_to_update',
        ],
    ]);

    $response->assertJsonPath('summary.total_students', 1);
    $response->assertJsonPath('summary.total_amount', 5000000);
});

it('returns empty preview when no unapplied payments exist', function () {
    $response = $this->actingAs($this->user)
        ->postJson(route('finance.payments.auto-allocate.preview'), [
            'priority_order' => ['tuition_term', 'egc_level_fee'],
        ]);

    $response->assertOk();
    $response->assertJsonPath('summary.total_students', 0);
    $response->assertJsonPath('summary.total_amount', 0);
});

it('validates priority_order is required for preview', function () {
    $response = $this->actingAs($this->user)
        ->postJson(route('finance.payments.auto-allocate.preview'), []);

    $response->assertStatus(422);
    $response->assertJsonPath('errors.0.field', 'priority_order');
});

it('executes auto-allocation and redirects with success', function () {
    $student = Student::factory()
        ->forCampus($this->campus)
        ->forProgram($this->program)
        ->state([
            'curriculum_version_id' => $this->curriculumVersion->id,
            'intake_semester_id' => $this->semester->id,
            'intake' => 1,
            'intake_mode' => 'sequential',
        ])
        ->create();

    $charge = FinanceCharge::create([
        'student_id' => $student->id,
        'semester_id' => $this->semester->id,
        'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
        'amount' => 10000000,
        'description' => 'Tuition test',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);

    // Create unpaid invoice and link charge to it
    $invoice = StudentInvoice::create([
        'student_id' => $student->id,
        'semester_id' => $this->semester->id,
        'invoice_number' => 'INV-TEST-002',
        'status' => 'pending',
        'due_date' => now()->addDays(30),
    ]);

    InvoiceLine::create([
        'invoice_id' => $invoice->id,
        'charge_id' => $charge->id,
        'amount_snapshot' => $charge->amount,
        'description_snapshot' => $charge->description,
    ]);

    Payment::create([
        'student_id' => $student->id,
        'amount' => 5000000,
        'status' => Payment::STATUS_COMPLETED,
        'paid_at' => now(),
        'source' => 'manual',
    ]);

    $response = $this->actingAs($this->user)
        ->post(route('finance.payments.auto-allocate'), [
            'priority_order' => ['tuition_term'],
        ]);

    $response->assertRedirect(route('finance.payments.index'));
    $response->assertSessionHas('success');
});
