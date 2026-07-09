<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\CurriculumVersion;
use App\Models\Program;
use App\Models\ScholarshipDefinition;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentScholarshipAward;
use App\Models\TuitionPlan;
use App\Models\TuitionPlanTerm;
use App\Models\Unit;
use App\Modules\Finance\Actions\Operations\GenerateBatchChargesAction;
use App\Modules\Finance\Models\DiscountAllocation;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\InvoiceDiscount;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\PaymentApplication;
use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Finance\Queries\Operations\PreviewChargeGenerationQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function seedTransitionStudentScenario(): array
{
    $campus = Campus::factory()->create();
    $program = Program::factory()->create();
    $semester = Semester::factory()->create([
        'code' => 'FALL2025',
        'name' => 'Fall2025',
        'start_date' => '2025-09-01 00:00:00',
        'end_date' => '2025-12-31 00:00:00',
        'is_active' => true,
        'is_archived' => false,
    ]);
    $curriculumVersion = CurriculumVersion::factory()
        ->forProgram($program)
        ->withEffectiveSemester($semester)
        ->create();

    session(['current_campus_id' => $campus->id]);
    app()->instance('campus', $campus);

    $student = Student::factory()
        ->forCampus($campus)
        ->forProgram($program)
        ->state([
            'student_id' => 'AUS10781',
            'full_name' => 'HENG HUNG LONG',
            'status' => 'intake_course',
            'curriculum_version_id' => $curriculumVersion->id,
            'intake_semester_id' => $semester->id,
            'intake' => 1,
            'intake_mode' => 'sequential',
            'intake_gc' => $semester->id,
            'intake_course' => (string) $semester->id,
            'intake_major' => $semester->id,
            'gc_to_course_transition_semester' => (string) $semester->id,
        ])
        ->create();

    $plan = TuitionPlan::create([
        'curriculum_version_id' => $curriculumVersion->id,
        'intake_semester_id' => $semester->id,
        'total_amount' => 180000000,
        'currency' => 'VND',
        'is_active' => true,
    ]);

    TuitionPlanTerm::create([
        'tuition_plan_id' => $plan->id,
        'term_number' => 1,
        'amount' => 45000000,
        'due_date' => '2025-12-13',
    ]);

    $paidInvoice = StudentInvoice::create([
        'invoice_number' => 'INV-EGC-PAID-001',
        'student_id' => $student->id,
        'billing_cycle_id' => null,
        'semester_id' => $semester->id,
        'status' => 'paid',
        'due_date' => now()->addDays(7),
    ]);

    $egcCharge = FinanceCharge::create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'charge_type' => FinanceCharge::TYPE_EGC_LEVEL_FEE,
        'amount' => 15000000,
        'description' => 'EGC Level 2 Fee',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);

    InvoiceLine::create([
        'invoice_id' => $paidInvoice->id,
        'charge_id' => $egcCharge->id,
        'amount_snapshot' => $egcCharge->amount,
        'description_snapshot' => $egcCharge->description,
    ]);

    return [$student, $semester, $paidInvoice];
}

function seedZeroAmountTuitionScenario(): array
{
    $campus = Campus::factory()->create();
    $program = Program::factory()->create();

    $semesterOne = Semester::factory()->create([
        'code' => 'FALL2025',
        'name' => 'Fall2025',
        'start_date' => '2025-09-01 00:00:00',
        'end_date' => '2025-12-31 00:00:00',
        'is_active' => false,
        'is_archived' => false,
    ]);

    $semesterTwo = Semester::factory()->create([
        'code' => 'SPRING2026',
        'name' => 'Spring2026',
        'start_date' => '2026-01-01 00:00:00',
        'end_date' => '2026-05-31 00:00:00',
        'is_active' => true,
        'is_archived' => false,
    ]);

    $curriculumVersion = CurriculumVersion::factory()
        ->forProgram($program)
        ->withEffectiveSemester($semesterOne)
        ->create();

    session(['current_campus_id' => $campus->id]);
    app()->instance('campus', $campus);

    $student = Student::factory()
        ->forCampus($campus)
        ->forProgram($program)
        ->state([
            'student_id' => 'AUSZERO1',
            'full_name' => 'Zero Tuition Student',
            'status' => 'intake_course',
            'curriculum_version_id' => $curriculumVersion->id,
            'intake_semester_id' => $semesterOne->id,
            'intake' => 1,
            'intake_mode' => 'sequential',
            'intake_course' => (string) $semesterOne->id,
            'intake_major' => $semesterOne->id,
        ])
        ->create();

    $plan = TuitionPlan::create([
        'curriculum_version_id' => $curriculumVersion->id,
        'intake_semester_id' => $semesterOne->id,
        'total_amount' => 45000000,
        'currency' => 'VND',
        'is_active' => true,
    ]);

    TuitionPlanTerm::create([
        'tuition_plan_id' => $plan->id,
        'term_number' => 1,
        'amount' => 0,
        'due_date' => '2025-10-01',
    ]);

    TuitionPlanTerm::create([
        'tuition_plan_id' => $plan->id,
        'term_number' => 2,
        'amount' => 45000000,
        'due_date' => '2026-02-01',
    ]);

    return [$student, $semesterOne, $semesterTwo];
}

function seedExistingEgcSemesterScenario(): array
{
    $campus = Campus::factory()->create();
    $program = Program::factory()->create();

    $fall = Semester::factory()->create([
        'code' => 'FALL2025',
        'name' => 'FALL2025',
        'start_date' => '2025-09-01 00:00:00',
        'end_date' => '2025-12-31 00:00:00',
        'is_active' => false,
        'is_archived' => false,
    ]);

    $spring = Semester::factory()->create([
        'code' => 'SPRING2026',
        'name' => 'SPRING2026',
        'start_date' => '2026-01-05 00:00:00',
        'end_date' => '2026-05-31 00:00:00',
        'is_active' => true,
        'is_archived' => false,
    ]);

    $curriculumVersion = CurriculumVersion::factory()
        ->forProgram($program)
        ->withEffectiveSemester($fall)
        ->create();

    session(['current_campus_id' => $campus->id]);
    app()->instance('campus', $campus);

    $student = Student::factory()
        ->forCampus($campus)
        ->forProgram($program)
        ->state([
            'student_id' => 'AUS118115',
            'full_name' => 'EGC Existing Student',
            'status' => 'intake_pre_uni_gc',
            'gc_current_level' => 4,
            'gc_total_levels' => 6,
            'curriculum_version_id' => $curriculumVersion->id,
            'intake_semester_id' => $fall->id,
            'intake' => 1,
            'intake_mode' => 'sequential',
        ])
        ->create();

    $springInvoice = StudentInvoice::create([
        'invoice_number' => 'INV-SPRING-EGC',
        'student_id' => $student->id,
        'billing_cycle_id' => null,
        'semester_id' => $spring->id,
        'status' => 'draft',
        'due_date' => now()->addDays(30),
    ]);

    $charge3 = FinanceCharge::create([
        'student_id' => $student->id,
        'semester_id' => $spring->id,
        'charge_type' => FinanceCharge::TYPE_EGC_LEVEL_FEE,
        'amount' => 15000000,
        'description' => 'EGC Level 3 Fee',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);

    $charge4 = FinanceCharge::create([
        'student_id' => $student->id,
        'semester_id' => $spring->id,
        'charge_type' => FinanceCharge::TYPE_EGC_LEVEL_FEE,
        'amount' => 15000000,
        'description' => 'EGC Level 4 Fee',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);

    InvoiceLine::create([
        'invoice_id' => $springInvoice->id,
        'charge_id' => $charge3->id,
        'amount_snapshot' => $charge3->amount,
        'description_snapshot' => $charge3->description,
    ]);

    InvoiceLine::create([
        'invoice_id' => $springInvoice->id,
        'charge_id' => $charge4->id,
        'amount_snapshot' => $charge4->amount,
        'description_snapshot' => $charge4->description,
    ]);

    return [$student, $spring, $springInvoice];
}

function seedEmptyReusableEgcInvoiceScenario(): array
{
    $campus = Campus::factory()->create();
    $program = Program::factory()->create();

    $fall = Semester::factory()->create([
        'code' => 'FALL2025',
        'name' => 'FALL2025',
        'start_date' => '2025-09-01 00:00:00',
        'end_date' => '2025-12-31 00:00:00',
        'is_active' => false,
        'is_archived' => false,
    ]);

    $spring = Semester::factory()->create([
        'code' => 'SPRING2026',
        'name' => 'SPRING2026',
        'start_date' => '2026-01-05 00:00:00',
        'end_date' => '2026-05-31 00:00:00',
        'is_active' => true,
        'is_archived' => false,
    ]);

    $curriculumVersion = CurriculumVersion::factory()
        ->forProgram($program)
        ->withEffectiveSemester($fall)
        ->create();

    session(['current_campus_id' => $campus->id]);
    app()->instance('campus', $campus);

    $student = Student::factory()
        ->forCampus($campus)
        ->forProgram($program)
        ->state([
            'student_id' => 'AUH110281',
            'full_name' => 'Empty Reusable EGC Invoice',
            'status' => 'intake_pre_uni_gc',
            'gc_current_level' => 3,
            'gc_total_levels' => 6,
            'curriculum_version_id' => $curriculumVersion->id,
            'intake_semester_id' => $fall->id,
            'intake' => 1,
            'intake_mode' => 'sequential',
        ])
        ->create();

    $springInvoice = StudentInvoice::create([
        'invoice_number' => 'INV-SPRING-EMPTY-EGC',
        'student_id' => $student->id,
        'billing_cycle_id' => null,
        'semester_id' => $spring->id,
        'status' => 'draft',
        'due_date' => now()->addDays(30),
    ]);

    Unit::create([
        'code' => 'EGC-L3',
        'name' => 'EGC Level 3',
        'credit_points' => 0,
        'retake_fee' => 0,
        'unit_type' => 'egc',
        'level' => 3,
        'base_fee' => 15000000,
    ]);

    Unit::create([
        'code' => 'EGC-L4',
        'name' => 'EGC Level 4',
        'credit_points' => 0,
        'retake_fee' => 0,
        'unit_type' => 'egc',
        'level' => 4,
        'base_fee' => 15000000,
    ]);

    return [$student, $spring, $springInvoice];
}

function seedFutureIntakeStudentScenario(): array
{
    $campus = Campus::factory()->create();
    $program = Program::factory()->create();

    $fall = Semester::factory()->create([
        'code' => 'FALL2025',
        'name' => 'Fall2025',
        'start_date' => '2025-09-01 00:00:00',
        'end_date' => '2025-12-31 00:00:00',
        'is_active' => false,
        'is_archived' => false,
    ]);

    $spring = Semester::factory()->create([
        'code' => 'SPRING2026',
        'name' => 'Spring2026',
        'start_date' => '2026-01-05 00:00:00',
        'end_date' => '2026-05-31 00:00:00',
        'is_active' => true,
        'is_archived' => false,
    ]);

    $curriculumVersion = CurriculumVersion::factory()
        ->forProgram($program)
        ->withEffectiveSemester($fall)
        ->create();

    session(['current_campus_id' => $campus->id]);
    app()->instance('campus', $campus);

    $student = Student::factory()
        ->forCampus($campus)
        ->forProgram($program)
        ->state([
            'student_id' => 'AUSFUTURE1',
            'full_name' => 'Future Intake Student',
            'status' => 'intake_course',
            'curriculum_version_id' => $curriculumVersion->id,
            'intake_semester_id' => $spring->id,
            'intake' => 2,
            'intake_mode' => 'sequential',
            'intake_course' => (string) $spring->id,
            'intake_major' => $spring->id,
        ])
        ->create();

    $plan = TuitionPlan::create([
        'curriculum_version_id' => $curriculumVersion->id,
        'intake_semester_id' => $spring->id,
        'total_amount' => 45000000,
        'currency' => 'VND',
        'is_active' => true,
    ]);

    TuitionPlanTerm::create([
        'tuition_plan_id' => $plan->id,
        'term_number' => 1,
        'amount' => 45000000,
        'due_date' => '2026-02-01',
    ]);

    return [$student, $fall];
}

function seedExistingTuitionInvoiceMissingScholarshipScenario(): array
{
    [$student, $semester] = seedTransitionStudentScenario();

    $scholarship = ScholarshipDefinition::create([
        'code' => 'ASIA_REUSE',
        'name' => 'Asia Reuse',
        'description' => 'Scholarship reuse test',
        'type' => 'percentage',
        'amount' => 20,
        'valid_from' => now()->subYear()->toDateString(),
        'valid_until' => now()->addYear()->toDateString(),
        'is_active' => true,
    ]);

    StudentScholarshipAward::create([
        'student_id' => $student->id,
        'scholarship_code' => $scholarship->code,
        'awarded_at' => now()->toDateString(),
    ]);

    $invoice = StudentInvoice::create([
        'invoice_number' => 'INV-TUITION-DRAFT-001',
        'student_id' => $student->id,
        'billing_cycle_id' => null,
        'semester_id' => $semester->id,
        'status' => 'draft',
        'due_date' => now()->addDays(30),
    ]);

    $tuitionCharge = FinanceCharge::create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
        'amount' => 45000000,
        'description' => 'Major Tuition (Installment 1)',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);

    InvoiceLine::create([
        'invoice_id' => $invoice->id,
        'charge_id' => $tuitionCharge->id,
        'amount_snapshot' => $tuitionCharge->amount,
        'description_snapshot' => $tuitionCharge->description,
    ]);

    return [$student, $semester, $invoice, $tuitionCharge, $scholarship];
}

function seedExistingPaidTuitionScenario(): array
{
    [$student, $semester] = seedTransitionStudentScenario();

    $scholarship = ScholarshipDefinition::create([
        'code' => 'ASIA_SKIP',
        'name' => 'Asia Skip',
        'description' => 'Scholarship skip test',
        'type' => 'percentage',
        'amount' => 10,
        'valid_from' => now()->subYear()->toDateString(),
        'valid_until' => now()->addYear()->toDateString(),
        'is_active' => true,
    ]);

    $award = StudentScholarshipAward::create([
        'student_id' => $student->id,
        'scholarship_code' => $scholarship->code,
        'awarded_at' => now()->toDateString(),
    ]);

    $invoice = StudentInvoice::create([
        'invoice_number' => 'INV-TUITION-PAID-001',
        'student_id' => $student->id,
        'billing_cycle_id' => null,
        'semester_id' => $semester->id,
        'status' => 'paid',
        'due_date' => now()->addDays(30),
        'subtotal' => 45000000,
        'discount_total' => 4500000,
        'total_amount' => 40500000,
        'paid_amount' => 40500000,
    ]);

    $tuitionCharge = FinanceCharge::create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
        'amount' => 45000000,
        'description' => 'Major Tuition (Installment 1)',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);

    InvoiceLine::create([
        'invoice_id' => $invoice->id,
        'charge_id' => $tuitionCharge->id,
        'amount_snapshot' => $tuitionCharge->amount,
        'description_snapshot' => $tuitionCharge->description,
    ]);

    InvoiceDiscount::create([
        'invoice_id' => $invoice->id,
        'discount_type' => 'scholarship',
        'discount_source' => StudentScholarshipAward::class,
        'description' => 'Scholarship: '.$scholarship->name,
        'amount' => 4500000,
        'status' => 'active',
        'reference_id' => $award->id,
    ]);

    DiscountAllocation::create([
        'invoice_discount_id' => $invoice->discounts()->firstOrFail()->id,
        'invoice_line_id' => $invoice->invoiceLines()->firstOrFail()->id,
        'amount' => 4500000,
        'entry_type' => 'allocation',
        'allocation_rule' => 'current_line_chronology',
    ]);

    $payment = Payment::create([
        'student_id' => $student->id,
        'amount' => 40500000,
        'method' => Payment::METHOD_BANK_TRANSFER,
        'source' => 'test',
        'paid_at' => now(),
        'status' => Payment::STATUS_COMPLETED,
    ]);

    PaymentApplication::create([
        'payment_id' => $payment->id,
        'invoice_line_id' => $invoice->invoiceLines()->firstOrFail()->id,
        'amount' => 40500000,
        'entry_type' => 'application',
        'applied_at' => now(),
    ]);

    $invoice->recalculateTotals();
    $invoice->refresh();

    return [$student, $semester, $invoice, $tuitionCharge];
}

it('marks transition students for a new invoice in preview when the only semester invoice is paid', function () {
    [$student, $semester] = seedTransitionStudentScenario();

    $result = app(PreviewChargeGenerationQuery::class)->handle([
        'semester_id' => $semester->id,
        'scope_type' => 'upload_list',
        'uploaded_student_ids' => [$student->student_id],
        'charge_types' => [FinanceCharge::TYPE_TUITION_TERM],
        'skip_if_issued_or_paid' => true,
        'only_update_draft' => true,
        'merge_invoice' => true,
    ]);

    expect($result['students'])->toHaveCount(1)
        ->and($result['students'][0]['student_id'])->toBe('AUS10781')
        ->and($result['students'][0]['estimated_amount'])->toBe(45000000.0)
        ->and($result['students'][0]['will_create_invoice'])->toBeTrue()
        ->and($result['students'][0]['warning'])->toContain('new invoice will be created');
});

it('does not preview or generate charges for students who have not reached their intake semester yet', function () {
    [$student, $fall] = seedFutureIntakeStudentScenario();

    $preview = app(PreviewChargeGenerationQuery::class)->handle([
        'semester_id' => $fall->id,
        'scope_type' => 'upload_list',
        'uploaded_student_ids' => [$student->student_id],
        'charge_types' => [FinanceCharge::TYPE_TUITION_TERM],
        'skip_if_issued_or_paid' => true,
        'only_update_draft' => true,
        'merge_invoice' => true,
    ]);

    $result = GenerateBatchChargesAction::run([
        'semester_id' => $fall->id,
        'scope_type' => 'upload_list',
        'uploaded_student_ids' => [$student->student_id],
        'charge_types' => [FinanceCharge::TYPE_TUITION_TERM],
        'skip_if_issued_or_paid' => true,
        'only_update_draft' => true,
        'merge_invoice' => true,
    ]);

    expect($preview['students'])->toHaveCount(0)
        ->and($preview['new_charges_count'])->toBe(0)
        ->and($result['created_invoices'])->toBe(0)
        ->and($result['updated_invoices'])->toBe(0)
        ->and($result['created_count'])->toBe(0)
        ->and(FinanceCharge::query()->where('student_id', $student->id)->count())->toBe(0)
        ->and(StudentInvoice::query()->where('student_id', $student->id)->count())->toBe(0);
});

it('creates a new tuition invoice for transition students even when an existing semester invoice is paid', function () {
    [$student, $semester, $paidInvoice] = seedTransitionStudentScenario();

    $result = GenerateBatchChargesAction::run([
        'semester_id' => $semester->id,
        'scope_type' => 'upload_list',
        'uploaded_student_ids' => [$student->student_id],
        'charge_types' => [FinanceCharge::TYPE_TUITION_TERM],
        'skip_if_issued_or_paid' => true,
        'only_update_draft' => true,
        'merge_invoice' => true,
    ]);

    $tuitionCharge = FinanceCharge::query()
        ->where('student_id', $student->id)
        ->where('semester_id', $semester->id)
        ->where('charge_type', FinanceCharge::TYPE_TUITION_TERM)
        ->first();

    $tuitionLine = InvoiceLine::query()
        ->where('charge_id', $tuitionCharge?->id)
        ->first();

    expect($result['created_invoices'])->toBe(1)
        ->and($result['updated_invoices'])->toBe(0)
        ->and($result['created_count'])->toBe(1)
        ->and(StudentInvoice::where('student_id', $student->id)->where('semester_id', $semester->id)->count())->toBe(2)
        ->and($paidInvoice->fresh()->status)->toBe('paid')
        ->and(InvoiceLine::where('invoice_id', $paidInvoice->id)->count())->toBe(1)
        ->and($tuitionCharge)->not->toBeNull()
        ->and((float) $tuitionCharge->amount)->toBe(45000000.0)
        ->and($tuitionLine)->not->toBeNull()
        ->and($tuitionLine->invoice_id)->not->toBe($paidInvoice->id)
        ->and($tuitionLine->invoice->status)->toBe('draft');
});

it('creates scholarship invoice discounts and discount allocations during charge generation', function () {
    [$student, $semester] = seedTransitionStudentScenario();

    $scholarship = ScholarshipDefinition::create([
        'code' => 'ASIA_PIONEER',
        'name' => 'Asia Pioneer',
        'description' => 'Scholarship test',
        'type' => 'percentage',
        'amount' => 40,
        'valid_from' => now()->subDay()->toDateString(),
        'valid_until' => now()->addDay()->toDateString(),
        'is_active' => true,
    ]);

    StudentScholarshipAward::create([
        'student_id' => $student->id,
        'scholarship_code' => $scholarship->code,
        'awarded_at' => now()->toDateString(),
    ]);

    $result = GenerateBatchChargesAction::run([
        'semester_id' => $semester->id,
        'scope_type' => 'upload_list',
        'uploaded_student_ids' => [$student->student_id],
        'charge_types' => [FinanceCharge::TYPE_TUITION_TERM],
        'skip_if_issued_or_paid' => true,
        'only_update_draft' => true,
        'merge_invoice' => true,
    ]);

    $tuitionCharge = FinanceCharge::query()
        ->where('student_id', $student->id)
        ->where('semester_id', $semester->id)
        ->where('charge_type', FinanceCharge::TYPE_TUITION_TERM)
        ->firstOrFail();

    $tuitionLine = InvoiceLine::query()
        ->where('charge_id', $tuitionCharge->id)
        ->firstOrFail();

    $discount = InvoiceDiscount::query()
        ->where('invoice_id', $tuitionLine->invoice_id)
        ->where('discount_type', 'scholarship')
        ->first();

    expect($result['created_invoices'])->toBe(1)
        ->and($discount)->not->toBeNull()
        ->and((float) $discount->amount)->toBe(18000000.0)
        ->and($discount->discount_source)->toBe('App\Models\StudentScholarshipAward')
        ->and(DiscountAllocation::query()->where('invoice_discount_id', $discount->id)->count())->toBeGreaterThan(0)
        ->and((float) DiscountAllocation::query()->where('invoice_discount_id', $discount->id)->sum('amount'))->toBe(18000000.0);
});

it('applies scholarship to an existing reusable tuition invoice without creating a new tuition charge', function () {
    [$student, $semester, $invoice, $tuitionCharge, $scholarship] = seedExistingTuitionInvoiceMissingScholarshipScenario();

    $preview = app(PreviewChargeGenerationQuery::class)->handle([
        'semester_id' => $semester->id,
        'scope_type' => 'upload_list',
        'uploaded_student_ids' => [$student->student_id],
        'charge_types' => [FinanceCharge::TYPE_TUITION_TERM],
        'skip_if_issued_or_paid' => true,
        'only_update_draft' => true,
        'merge_invoice' => true,
    ]);

    $result = GenerateBatchChargesAction::run([
        'semester_id' => $semester->id,
        'scope_type' => 'upload_list',
        'uploaded_student_ids' => [$student->student_id],
        'charge_types' => [FinanceCharge::TYPE_TUITION_TERM],
        'skip_if_issued_or_paid' => true,
        'only_update_draft' => true,
        'merge_invoice' => true,
    ]);

    $discount = InvoiceDiscount::query()
        ->where('invoice_id', $invoice->id)
        ->where('discount_type', 'scholarship')
        ->first();

    expect($preview['students'])->toHaveCount(1)
        ->and($preview['students'][0]['will_create_invoice'])->toBeFalse()
        ->and($preview['students'][0]['estimated_amount'])->toBe(-9000000.0)
        ->and(collect($preview['students'][0]['breakdown'])->pluck('label')->all())->not->toContain('Tuition (Skipped)')
        ->and(collect($preview['students'][0]['breakdown'])->pluck('label')->all())->toContain("Scholarship ({$scholarship->code})")
        ->and($result['created_invoices'])->toBe(0)
        ->and($result['updated_invoices'])->toBe(1)
        ->and($result['created_count'])->toBe(0)
        ->and(FinanceCharge::query()
            ->where('student_id', $student->id)
            ->where('semester_id', $semester->id)
            ->where('charge_type', FinanceCharge::TYPE_TUITION_TERM)
            ->count())->toBe(1)
        ->and($discount)->not->toBeNull()
        ->and((float) $discount->amount)->toBe(9000000.0)
        ->and(DiscountAllocation::query()->where('invoice_discount_id', $discount->id)->count())->toBeGreaterThan(0)
        ->and((float) $invoice->fresh()->discount_total)->toBe(9000000.0)
        ->and((float) $invoice->fresh()->total_amount)->toBe(36000000.0)
        ->and(InvoiceLine::query()->where('charge_id', $tuitionCharge->id)->count())->toBe(1);
});

it('skips preview and generate completely when tuition charge already exists on a paid invoice with no pending invoice changes', function () {
    [$student, $semester, $invoice, $tuitionCharge] = seedExistingPaidTuitionScenario();

    $preview = app(PreviewChargeGenerationQuery::class)->handle([
        'semester_id' => $semester->id,
        'scope_type' => 'upload_list',
        'uploaded_student_ids' => [$student->student_id],
        'charge_types' => [FinanceCharge::TYPE_TUITION_TERM],
        'skip_if_issued_or_paid' => true,
        'only_update_draft' => true,
        'merge_invoice' => true,
    ]);

    $result = GenerateBatchChargesAction::run([
        'semester_id' => $semester->id,
        'scope_type' => 'upload_list',
        'uploaded_student_ids' => [$student->student_id],
        'charge_types' => [FinanceCharge::TYPE_TUITION_TERM],
        'skip_if_issued_or_paid' => true,
        'only_update_draft' => true,
        'merge_invoice' => true,
    ]);

    expect($preview['students'])->toHaveCount(0)
        ->and($result['created_invoices'])->toBe(0)
        ->and($result['updated_invoices'])->toBe(0)
        ->and($result['created_count'])->toBe(0)
        ->and($result['failed_count'])->toBe(0)
        ->and(StudentInvoice::query()->where('student_id', $student->id)->where('semester_id', $semester->id)->count())->toBe(2)
        ->and(InvoiceLine::query()->where('charge_id', $tuitionCharge->id)->count())->toBe(1);
});

it('does not create invoice for zero tuition term amounts', function () {
    [$student, $semesterOne] = seedZeroAmountTuitionScenario();

    $preview = app(PreviewChargeGenerationQuery::class)->handle([
        'semester_id' => $semesterOne->id,
        'scope_type' => 'upload_list',
        'uploaded_student_ids' => [$student->student_id],
        'charge_types' => [FinanceCharge::TYPE_TUITION_TERM],
        'skip_if_issued_or_paid' => true,
        'only_update_draft' => true,
        'merge_invoice' => true,
    ]);

    $result = GenerateBatchChargesAction::run([
        'semester_id' => $semesterOne->id,
        'scope_type' => 'upload_list',
        'uploaded_student_ids' => [$student->student_id],
        'charge_types' => [FinanceCharge::TYPE_TUITION_TERM],
        'skip_if_issued_or_paid' => true,
        'only_update_draft' => true,
        'merge_invoice' => true,
    ]);

    expect($preview['students'])->toHaveCount(0)
        ->and($result['created_invoices'])->toBe(0)
        ->and(FinanceCharge::query()->where('student_id', $student->id)->where('charge_type', FinanceCharge::TYPE_TUITION_TERM)->count())->toBe(0)
        ->and(StudentInvoice::query()->where('student_id', $student->id)->count())->toBe(0);
});

it('counts installment index by chargeable tuition terms instead of existing charge count', function () {
    [$student, , $semesterTwo] = seedZeroAmountTuitionScenario();

    $preview = app(PreviewChargeGenerationQuery::class)->handle([
        'semester_id' => $semesterTwo->id,
        'scope_type' => 'upload_list',
        'uploaded_student_ids' => [$student->student_id],
        'charge_types' => [FinanceCharge::TYPE_TUITION_TERM],
        'skip_if_issued_or_paid' => true,
        'only_update_draft' => true,
        'merge_invoice' => true,
    ]);

    $result = GenerateBatchChargesAction::run([
        'semester_id' => $semesterTwo->id,
        'scope_type' => 'upload_list',
        'uploaded_student_ids' => [$student->student_id],
        'charge_types' => [FinanceCharge::TYPE_TUITION_TERM],
        'skip_if_issued_or_paid' => true,
        'only_update_draft' => true,
        'merge_invoice' => true,
    ]);

    $charge = FinanceCharge::query()
        ->where('student_id', $student->id)
        ->where('semester_id', $semesterTwo->id)
        ->where('charge_type', FinanceCharge::TYPE_TUITION_TERM)
        ->firstOrFail();

    expect($preview['students'])->toHaveCount(1)
        ->and($preview['students'][0]['estimated_amount'])->toBe(45000000.0)
        ->and($result['created_invoices'])->toBe(1)
        ->and($charge->description)->toBe('Major Tuition (Installment 1)');
});

it('does not preview or generate new egc charges when that semester already has egc package issued', function () {
    [$student, $spring, $springInvoice] = seedExistingEgcSemesterScenario();

    $preview = app(PreviewChargeGenerationQuery::class)->handle([
        'semester_id' => $spring->id,
        'scope_type' => 'upload_list',
        'uploaded_student_ids' => [$student->student_id],
        'charge_types' => [FinanceCharge::TYPE_EGC_LEVEL_FEE],
        'skip_if_issued_or_paid' => true,
        'only_update_draft' => true,
        'merge_invoice' => true,
    ]);

    $result = GenerateBatchChargesAction::run([
        'semester_id' => $spring->id,
        'scope_type' => 'upload_list',
        'uploaded_student_ids' => [$student->student_id],
        'charge_types' => [FinanceCharge::TYPE_EGC_LEVEL_FEE],
        'skip_if_issued_or_paid' => true,
        'only_update_draft' => true,
        'merge_invoice' => true,
    ]);

    expect($preview['students'])->toHaveCount(0)
        ->and($result['created_count'])->toBe(0)
        ->and(FinanceCharge::query()->where('student_id', $student->id)->where('semester_id', $spring->id)->where('charge_type', FinanceCharge::TYPE_EGC_LEVEL_FEE)->count())->toBe(2)
        ->and(InvoiceLine::query()->where('invoice_id', $springInvoice->id)->count())->toBe(2);
});

it('reuses an empty draft invoice for egc generation when no egc lines exist yet in that semester', function () {
    [$student, $spring, $springInvoice] = seedEmptyReusableEgcInvoiceScenario();

    $preview = app(PreviewChargeGenerationQuery::class)->handle([
        'semester_id' => $spring->id,
        'scope_type' => 'upload_list',
        'uploaded_student_ids' => [$student->student_id],
        'charge_types' => [FinanceCharge::TYPE_EGC_LEVEL_FEE],
        'skip_if_issued_or_paid' => true,
        'only_update_draft' => true,
        'merge_invoice' => true,
    ]);

    $result = GenerateBatchChargesAction::run([
        'semester_id' => $spring->id,
        'scope_type' => 'upload_list',
        'uploaded_student_ids' => [$student->student_id],
        'charge_types' => [FinanceCharge::TYPE_EGC_LEVEL_FEE],
        'skip_if_issued_or_paid' => true,
        'only_update_draft' => true,
        'merge_invoice' => true,
    ]);

    expect($result['created_invoices'])->toBe(0)
        ->and($result['updated_invoices'])->toBe(1)
        ->and(InvoiceLine::query()->where('invoice_id', $springInvoice->id)->count())->toBe(2)
        ->and(FinanceCharge::query()->where('student_id', $student->id)->where('semester_id', $spring->id)->where('charge_type', FinanceCharge::TYPE_EGC_LEVEL_FEE)->count())->toBe(2);

    if ($preview['students'] !== []) {
        expect($preview['students'][0]['will_create_invoice'])->toBeFalse()
            ->and($preview['students'][0]['estimated_amount'])->toBe(30000000.0);
    }
});

it('regenerates a tuition charge after the prior one was voided (FIN-05)', function () {
    [$student, $semester] = seedTransitionStudentScenario();

    // First generation creates the tuition charge.
    GenerateBatchChargesAction::run([
        'semester_id' => $semester->id,
        'scope_type' => 'upload_list',
        'uploaded_student_ids' => [$student->student_id],
        'charge_types' => [FinanceCharge::TYPE_TUITION_TERM],
        'skip_if_issued_or_paid' => true,
        'only_update_draft' => true,
        'merge_invoice' => true,
    ]);

    $firstCharge = FinanceCharge::query()
        ->where('student_id', $student->id)
        ->where('semester_id', $semester->id)
        ->where('charge_type', FinanceCharge::TYPE_TUITION_TERM)
        ->firstOrFail();

    // Void it (e.g. correction). The voided charge must not block regeneration
    // nor be re-linked as if active.
    $firstCharge->update(['status' => FinanceCharge::STATUS_VOID, 'voided_at' => now()]);

    GenerateBatchChargesAction::run([
        'semester_id' => $semester->id,
        'scope_type' => 'upload_list',
        'uploaded_student_ids' => [$student->student_id],
        'charge_types' => [FinanceCharge::TYPE_TUITION_TERM],
        'skip_if_issued_or_paid' => true,
        'only_update_draft' => true,
        'merge_invoice' => true,
    ]);

    $activeCharges = FinanceCharge::query()
        ->where('student_id', $student->id)
        ->where('semester_id', $semester->id)
        ->where('charge_type', FinanceCharge::TYPE_TUITION_TERM)
        ->where('status', FinanceCharge::STATUS_ACTIVE)
        ->get();

    expect($activeCharges)->toHaveCount(1)
        ->and($activeCharges->first()->id)->not->toBe($firstCharge->id)
        ->and((float) $activeCharges->first()->amount)->toBe(45000000.0)
        ->and($firstCharge->fresh()->status)->toBe(FinanceCharge::STATUS_VOID);
});

function seedNoUnitEgcScenario(): array
{
    $campus = Campus::factory()->create();
    $program = Program::factory()->create();

    $fall = Semester::factory()->create([
        'code' => 'FALL2025NU',
        'name' => 'FALL2025NU',
        'start_date' => '2025-09-01 00:00:00',
        'end_date' => '2025-12-31 00:00:00',
        'is_active' => false,
        'is_archived' => false,
    ]);

    $spring = Semester::factory()->create([
        'code' => 'SPRING2026NU',
        'name' => 'SPRING2026NU',
        'start_date' => '2026-01-05 00:00:00',
        'end_date' => '2026-05-31 00:00:00',
        'is_active' => true,
        'is_archived' => false,
    ]);

    $curriculumVersion = CurriculumVersion::factory()
        ->forProgram($program)
        ->withEffectiveSemester($fall)
        ->create();

    session(['current_campus_id' => $campus->id]);
    app()->instance('campus', $campus);

    $student = Student::factory()
        ->forCampus($campus)
        ->forProgram($program)
        ->state([
            'student_id' => 'AUNOUNIT1',
            'full_name' => 'No Unit EGC Student',
            'status' => 'intake_pre_uni_gc',
            'gc_current_level' => 3,
            'gc_total_levels' => 6,
            'curriculum_version_id' => $curriculumVersion->id,
            'intake_semester_id' => $fall->id,
            'intake' => 1,
            'intake_mode' => 'sequential',
        ])
        ->create();

    // Deliberately NO egc Unit rows for levels 3/4 → EgcLevelFeeResolver fallback.
    return [$student, $spring];
}

it('creates two distinct EGC charges on the Unit-fee fallback instead of collapsing levels (FIN-06 fallback)', function () {
    [$student, $spring] = seedNoUnitEgcScenario();

    GenerateBatchChargesAction::run([
        'semester_id' => $spring->id,
        'scope_type' => 'upload_list',
        'uploaded_student_ids' => [$student->student_id],
        'charge_types' => [FinanceCharge::TYPE_EGC_LEVEL_FEE],
        'skip_if_issued_or_paid' => true,
        'only_update_draft' => true,
        'merge_invoice' => true,
    ]);

    $charges = FinanceCharge::query()
        ->where('student_id', $student->id)
        ->where('semester_id', $spring->id)
        ->where('charge_type', FinanceCharge::TYPE_EGC_LEVEL_FEE)
        ->where('status', FinanceCharge::STATUS_ACTIVE)
        ->orderBy('id')
        ->get();

    // Levels 3 and 4 must be TWO distinct charges (not one reused), each at the
    // 15M fallback, with level-distinct descriptions.
    expect($charges)->toHaveCount(2)
        ->and($charges->pluck('description')->all())->toEqual(['EGC Level 3 Fee', 'EGC Level 4 Fee'])
        ->and($charges->pluck('amount')->map(fn ($a) => (float) $a)->all())->toEqual([15_000_000.0, 15_000_000.0]);
});

/**
 * FIN-REV-020-02 (M2): make a student's semester enrollment non-billable the way
 * a recorded FULL-scope defer does — a `registration_status = 'defer'` course
 * registration. The student keeps status 'intake_course' so it still reaches
 * charge generation/preview; the guard must suppress billing from the
 * registration signal alone, so a voided obligation is never resurrected
 * regardless of fee policy (PRESERVE or FORFEIT).
 */
function seedDeferredEnrollment(int $studentId, int $semesterId): CourseRegistration
{
    $offering = CourseOffering::factory()->create(['semester_id' => $semesterId]);

    return CourseRegistration::create([
        'student_id' => $studentId,
        'course_offering_id' => $offering->id,
        'semester_id' => $semesterId,
        'registration_status' => 'defer',
        'registration_date' => now(),
        'registration_method' => 'admin_override',
        'credit_hours' => 3,
        'credit_points' => 3,
        'attempt_number' => 1,
    ]);
}

it('does not generate tuition for a deferred-enrollment student (no resurrection of a voided obligation)', function () {
    [$student, $semester] = seedTransitionStudentScenario();
    seedDeferredEnrollment($student->id, $semester->id);

    $result = GenerateBatchChargesAction::run([
        'semester_id' => $semester->id,
        'scope_type' => 'upload_list',
        'uploaded_student_ids' => [$student->student_id],
        'charge_types' => [FinanceCharge::TYPE_TUITION_TERM],
        'skip_if_issued_or_paid' => true,
        'only_update_draft' => true,
        'merge_invoice' => true,
    ]);

    $tuitionCharges = FinanceCharge::query()
        ->where('student_id', $student->id)
        ->where('semester_id', $semester->id)
        ->where('charge_type', FinanceCharge::TYPE_TUITION_TERM)
        ->where('status', FinanceCharge::STATUS_ACTIVE)
        ->count();

    expect($tuitionCharges)->toBe(0)
        ->and($result['created_count'])->toBe(0);
});

it('previews no tuition for a deferred-enrollment student', function () {
    [$student, $semester] = seedTransitionStudentScenario();
    seedDeferredEnrollment($student->id, $semester->id);

    $preview = app(PreviewChargeGenerationQuery::class)->handle([
        'semester_id' => $semester->id,
        'scope_type' => 'upload_list',
        'uploaded_student_ids' => [$student->student_id],
        'charge_types' => [FinanceCharge::TYPE_TUITION_TERM],
        'skip_if_issued_or_paid' => true,
        'only_update_draft' => true,
        'merge_invoice' => true,
    ]);

    $row = collect($preview['students'])->firstWhere('student_id', $student->student_id);

    expect($row)->not->toBeNull()
        ->and((float) $row['estimated_amount'])->toBe(0.0)
        ->and($row['will_create_invoice'])->toBeFalse();
});
