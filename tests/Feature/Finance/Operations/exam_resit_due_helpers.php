<?php

declare(strict_types=1);

use App\Models\ExamResitAttempt;
use App\Models\ExamResitSession;
use App\Models\ExamRoomSlot;
use App\Models\Room;
use App\Models\Student;
use App\Modules\Finance\Actions\CreateExamResitChargeSimpleAction;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Dng\Models\DngPaymentRequestCharge;
use App\Modules\Finance\Models\FinanceCharge;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Shared fixtures for ACAD-RET-002 exam-resit Due Reminders tests. Complements
 * the Academic `makeApprovedExamResitAttempt` / `payExamResitChargeFully`
 * helpers by adding the Finance-side charge → pushed-DNG linkage and the
 * scheduled-sitting context the overdue clock is derived from.
 */

/**
 * Schedule an approved attempt into a real exam session/room slot so the
 * classifier can derive the overdue deadline from the sitting time.
 */
function scheduleExamResitAttemptInSlot(
    ExamResitAttempt $attempt,
    string|CarbonInterface $examDate,
    string $startTime = '09:00:00',
    string $endTime = '11:00:00',
): ExamResitAttempt {
    $examDate = $examDate instanceof CarbonInterface ? $examDate->toDateString() : $examDate;

    $room = Room::factory()->create(['campus_id' => $attempt->campus_id]);

    $slot = ExamRoomSlot::factory()->create([
        'campus_id' => $attempt->campus_id,
        'room_id' => $room->id,
        'exam_date' => $examDate,
        'start_time' => $startTime,
        'end_time' => $endTime,
    ]);

    $session = ExamResitSession::factory()->create([
        'exam_room_slot_id' => $slot->id,
        'unit_id' => $attempt->unit_id,
        'semester_id' => $attempt->operation_semester_id,
        'campus_id' => $attempt->campus_id,
        'expected_candidates' => 10,
    ]);

    $attempt->update([
        'status' => ExamResitAttempt::STATUS_SCHEDULED,
        'exam_resit_session_id' => $session->id,
        'scheduled_at' => now(),
    ]);

    return $attempt->fresh();
}

/**
 * Create the HQ FinanceCharge (exam_resit_fee) for an approved attempt via the
 * real Finance action, returning the persisted charge.
 */
function createExamResitChargeFor(ExamResitAttempt $attempt): FinanceCharge
{
    ensureExamResitPricingCatalog((float) ($attempt->fee_amount ?: 750_000));

    $updated = app(CreateExamResitChargeSimpleAction::class)->handle(['attempt_id' => $attempt->id]);

    return FinanceCharge::findOrFail($updated->finance_charge_id);
}

/**
 * Wave 7: exam_resit_fee is CatalogFixed — SimpleAction intake needs a catalog rule.
 */
function ensureExamResitPricingCatalog(float $amount = 750_000): void
{
    if (DB::table('finance_pricing_catalog_items')
        ->where('obligation_type', FinanceCharge::TYPE_EXAM_RESIT_FEE)
        ->where('is_active', true)
        ->exists()) {
        return;
    }

    DB::table('finance_pricing_catalog_items')->insert([
        'obligation_type' => FinanceCharge::TYPE_EXAM_RESIT_FEE,
        'amount' => $amount,
        'currency' => 'VND',
        'rule_version' => 'exam_resit_fee:v1',
        'description' => 'Fixed resit fee',
        'is_active' => true,
        'effective_from' => now()->subDay(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/**
 * Create an active pushed DNG request that collects an exam-resit charge,
 * linked both via the convenience finance_charge_id and the charge pivot.
 *
 * @param  array<string, mixed>  $overrides
 */
function createPushedDngForExamResitCharge(FinanceCharge $charge, array $overrides = []): DngPaymentRequest
{
    $student = Student::find($charge->student_id);

    $request = DngPaymentRequest::create(array_merge([
        'student_id' => $charge->student_id,
        'campus_code' => 'TEST',
        'student_code' => $student?->student_id ?? 'PTL'.$charge->id,
        'fee_type' => 'PTL',
        'description' => 'Phí thi lại',
        'semester_id' => $charge->semester_id,
        'due_date' => now()->subDays(3),
        'item_id' => 'PTL-ITEM-'.$charge->id,
        'amount' => $charge->amount,
        'status' => DngPaymentRequest::STATUS_PUSHED_TO_DNG,
        'finance_charge_id' => $charge->id,
    ], $overrides));

    DngPaymentRequestCharge::create([
        'dng_payment_request_id' => $request->id,
        'finance_charge_id' => $charge->id,
        'amount' => $charge->amount,
    ]);

    return $request;
}
