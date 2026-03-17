<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\CurriculumVersion;
use App\Models\FinanceCharge;
use App\Models\InvoiceLine;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentInvoice;
use App\Models\TuitionPlan;
use App\Models\TuitionPlanTerm;
use App\Modules\Finance\Actions\Operations\GenerateBatchChargesAction;
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
