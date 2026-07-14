<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions\Egc;

use App\Models\Student;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceCreditEntitlement;
use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Finance\Support\Entitlement\FinanceEntitlementType;
use App\Shared\Contracts\Finance\DTO\FinanceIntakeData;
use App\Shared\Contracts\Finance\Enums\FinancialEffect;
use App\Shared\Contracts\Finance\FinanceIntakeContract;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Apply -15M EGC early major-entry credit via FinanceCreditEntitlement + credit applications.
 * Never writes a negative FinanceCharge (ADR-0030 / wave 5).
 */
class ApplyEgcMajorEntryCreditAction
{
    /** Absolute credit amount in VND (positive). */
    public const CREDIT_AMOUNT = 15_000_000;

    public const SOURCE_SYSTEM = 'finance';

    public const SOURCE_KIND = 'egc_major_entry_credit';

    /**
     * Apply EGC exempt credit to a transitioned student's invoice.
     */
    public static function run(int $studentId, int $semesterId): FinanceCreditEntitlement
    {
        return DB::transaction(function () use ($studentId, $semesterId) {
            $student = Student::findOrFail($studentId);

            // Guard: student must have transitioned out of intake_pre_uni_gc
            if ($student->status === 'intake_pre_uni_gc') {
                throw ValidationException::withMessages([
                    'student_id' => ['Student is still an active EGC student and has not transitioned to major entry.'],
                ]);
            }

            // Guard: student must have an existing invoice for this semester
            $invoice = StudentInvoice::where('student_id', $studentId)
                ->where('semester_id', $semesterId)
                ->first();

            if (! $invoice) {
                throw ValidationException::withMessages([
                    'semester_id' => ['No invoice found for this student in the selected semester. Cannot apply credit.'],
                ]);
            }

            // Guard: double-apply — entitlement path or legacy negative charge
            $existingEntitlement = FinanceCreditEntitlement::query()
                ->where('source_system', self::SOURCE_SYSTEM)
                ->where('source_kind', self::SOURCE_KIND)
                ->where('source_ref', self::mintSourceRef($studentId, $semesterId))
                ->where('entitlement_type', FinanceEntitlementType::EgcExemptCredit)
                ->first();

            if ($existingEntitlement instanceof FinanceCreditEntitlement) {
                throw ValidationException::withMessages([
                    'student_id' => ['An EGC exempt credit has already been applied for this student and semester.'],
                ]);
            }

            $existingLegacyCharge = FinanceCharge::where('student_id', $studentId)
                ->where('semester_id', $semesterId)
                ->where('charge_type', FinanceEntitlementType::EgcExemptCredit)
                ->where('status', FinanceCharge::STATUS_ACTIVE)
                ->first();

            if ($existingLegacyCharge) {
                throw ValidationException::withMessages([
                    'student_id' => ['An EGC exempt credit has already been applied for this student and semester.'],
                ]);
            }

            $result = app(FinanceIntakeContract::class)->requestCredit(new FinanceIntakeData(
                source_system: self::SOURCE_SYSTEM,
                source_kind: self::SOURCE_KIND,
                source_ref: self::mintSourceRef($studentId, $semesterId),
                financial_effect: FinancialEffect::Credit,
                obligation_type: FinanceEntitlementType::EgcExemptCredit,
                facts: [
                    'student_id' => $studentId,
                    'semester_id' => $semesterId,
                    'amount' => self::CREDIT_AMOUNT,
                    'invoice_id' => (int) $invoice->id,
                    'description' => 'EGC Early Major Entry Credit',
                ],
            ));

            if ($result->finance_credit_entitlement_id === null) {
                throw new RuntimeException('EGC major-entry credit intake did not materialize a FinanceCreditEntitlement.');
            }

            if ($result->credit_application_ids === []) {
                throw ValidationException::withMessages([
                    'semester_id' => ['No billable invoice lines with remaining capacity were found for this student and semester. Cannot apply EGC exempt credit.'],
                ]);
            }

            $entitlement = FinanceCreditEntitlement::query()
                ->findOrFail($result->finance_credit_entitlement_id);

            $invoice->recalculateTotals();

            return $entitlement;
        });
    }

    public static function mintSourceRef(int $studentId, int $semesterId): string
    {
        return "egc_exempt_credit:student:{$studentId}:semester:{$semesterId}";
    }
}
