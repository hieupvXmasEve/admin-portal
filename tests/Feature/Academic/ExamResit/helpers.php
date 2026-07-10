<?php

declare(strict_types=1);

use App\Models\AcademicRecord;
use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\ExamResitAttempt;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Unit;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\PaymentApplication;
use App\Modules\Finance\Queries\Dng\ListDngWorklistQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Build an Academic-approved exam-resit attempt (thi lại) source that is waiting
 * for HQ fee creation. Mirrors the staff-created lifecycle entry point without
 * re-running eligibility validation, so Finance/HQ slices can build sources directly.
 *
 * @param  array<string, mixed>  $overrides
 */
function makeApprovedExamResitAttempt(
    Student $student,
    Campus $campus,
    Semester $semester,
    array $overrides = [],
): ExamResitAttempt {
    $unit = $overrides['unit'] ?? Unit::factory()->create();
    unset($overrides['unit']);

    $courseOffering = CourseOffering::factory()->create([
        'semester_id' => $semester->id,
        'unit_id' => $unit->id,
        'campus_id' => $campus->id,
    ]);

    $record = AcademicRecord::factory()->create([
        'student_id' => $student->id,
        'campus_id' => $campus->id,
        'semester_id' => $semester->id,
        'unit_id' => $unit->id,
        'course_offering_id' => $courseOffering->id,
        'completion_status' => 'failed',
        'grade_status' => 'final',
        'is_passed' => false,
        'failure_reason' => AcademicRecord::FAILURE_GRADE_FAILED,
    ]);

    $feeAmount = (float) ($overrides['fee_amount'] ?? 750_000);

    // Wave 7: CreateExamResitChargeSimpleAction goes through CatalogFixed intake.
    if (! DB::table('finance_pricing_catalog_items')
        ->where('obligation_type', FinanceCharge::TYPE_EXAM_RESIT_FEE)
        ->where('is_active', true)
        ->exists()) {
        DB::table('finance_pricing_catalog_items')->insert([
            'obligation_type' => FinanceCharge::TYPE_EXAM_RESIT_FEE,
            'amount' => $feeAmount,
            'currency' => 'VND',
            'rule_version' => 'exam_resit_fee:v1',
            'description' => 'Fixed resit fee',
            'is_active' => true,
            'effective_from' => now()->subDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    return ExamResitAttempt::create(array_merge([
        'student_id' => $student->id,
        'academic_record_id' => $record->id,
        'original_course_offering_id' => $courseOffering->id,
        'unit_id' => $unit->id,
        'campus_id' => $campus->id,
        'original_semester_id' => $semester->id,
        'operation_semester_id' => $semester->id,
        'charge_semester_id' => $semester->id,
        'request_origin' => ExamResitAttempt::REQUEST_ORIGIN_STAFF,
        'status' => ExamResitAttempt::STATUS_APPROVED,
        'request_sequence' => 1,
        'attempt_number' => null,
        'approved_at' => now(),
        'hq_fee_status' => ExamResitAttempt::HQ_FEE_PENDING,
        'fee_amount' => $feeAmount,
        'exam_resit_fee_snapshot' => $feeAmount,
    ], $overrides));
}

/**
 * Fully pay an exam-resit FinanceCharge through canonical Finance evidence
 * (payment + payment_application against its active invoice line), so the paid
 * syncer can detect it via FinanceCharge::is_fully_paid.
 */
function payExamResitChargeFully(FinanceCharge $charge): void
{
    $line = InvoiceLine::query()
        ->where('charge_id', $charge->id)
        ->where('status', 'active')
        ->firstOrFail();

    $payment = Payment::create([
        'student_id' => $charge->student_id,
        'amount' => $charge->amount,
        'method' => Payment::METHOD_IMPORT,
        'source' => 'import',
        'paid_at' => now(),
        'status' => Payment::STATUS_COMPLETED,
    ]);

    PaymentApplication::create([
        'payment_id' => $payment->id,
        'invoice_line_id' => $line->id,
        'amount' => $charge->amount,
        'entry_type' => 'application',
        'applied_at' => now(),
    ]);
}

/**
 * Run the DNG worklist query with a plain GET request (no controller/auth glue),
 * mirroring how Finance worklist queries are exercised in tests.
 *
 * @param  array<string, mixed>  $params
 * @return array<string, mixed>
 */
function runExamResitDngWorklist(array $params = []): array
{
    $request = Request::create('/finance/operations/dng-worklist', 'GET', array_merge(
        ['dng_fee_type' => 'PTL'],
        $params,
    ));

    return app(ListDngWorklistQuery::class)->handle($request);
}
