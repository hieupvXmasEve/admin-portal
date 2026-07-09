<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions\Major;

use App\Models\Student;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Support\StudentChargeTimingResolver;
use App\Shared\Contracts\Finance\DTO\FinanceIntakeData;
use App\Shared\Contracts\Finance\DTO\FinanceIntakeResult;
use App\Shared\Contracts\Finance\Enums\FinancialEffect;
use App\Shared\Contracts\Finance\FinanceIntakeContract;
use RuntimeException;

/**
 * Finance-owned tuition_term intake client used by Batch Studio major generation
 * and legacy batch charge generation.
 *
 * Mints source_ref, submits pricing facts only (never amount/currency), and lets
 * FinanceIntakeContract price + materialize the debit read model.
 */
class SubmitTuitionTermDebitAction
{
    public const SOURCE_SYSTEM = 'finance';

    public const SOURCE_KIND_BATCH_STUDIO = 'batch_studio';

    public const SOURCE_KIND_LEGACY_TUITION = 'legacy_tuition';

    public function __construct(
        private readonly FinanceIntakeContract $intake,
        private readonly StudentChargeTimingResolver $timingResolver,
    ) {}

    /**
     * @param  array{
     *     source_kind?: string,
     *     due_date?: string|null,
     *     invoice_id?: int|null,
     *     description?: string|null,
     * }  $options
     */
    public function handle(Student $student, int $semesterId, array $options = []): FinanceIntakeResult
    {
        $sourceKind = $options['source_kind'] ?? self::SOURCE_KIND_BATCH_STUDIO;

        if (! in_array($sourceKind, [
            self::SOURCE_KIND_BATCH_STUDIO,
            self::SOURCE_KIND_LEGACY_TUITION,
            'major_batch',
        ], true)) {
            throw new RuntimeException("Unsupported tuition source_kind [{$sourceKind}].");
        }

        $termData = $this->timingResolver->getTuitionTermData($student, $semesterId);
        $amount = $termData['amount'] ?? null;

        // Zero-amount / missing term rows stay a skip — do not create empty obligations.
        if ($amount === null || (float) $amount <= 0) {
            throw new RuntimeException(
                "Tuition term is not chargeable for student {$student->id} in semester {$semesterId}."
            );
        }

        $termNumber = $termData['term_number'] ?? null;
        if ($termNumber === null) {
            throw new RuntimeException(
                "Unable to resolve tuition term_number for student {$student->id} in semester {$semesterId}."
            );
        }

        $termIdx = $termData['chargeable_term_index'] ?? $termNumber;
        $description = $options['description']
            ?? "Major Tuition (Installment {$termIdx})";

        $facts = [
            'student_id' => (int) $student->id,
            'semester_id' => $semesterId,
            'curriculum_version_id' => (int) $student->curriculum_version_id,
            'intake_semester_id' => (int) $student->intake_semester_id,
            'term_number' => (int) $termNumber,
            'chargeable_term_index' => (int) $termIdx,
            'description' => $description,
        ];

        if (isset($options['due_date']) && is_string($options['due_date']) && $options['due_date'] !== '') {
            $facts['due_date'] = $options['due_date'];
        }

        if (isset($options['invoice_id']) && is_numeric($options['invoice_id']) && (int) $options['invoice_id'] > 0) {
            $facts['invoice_id'] = (int) $options['invoice_id'];
        }

        return $this->intake->request(new FinanceIntakeData(
            source_system: self::SOURCE_SYSTEM,
            source_kind: $sourceKind,
            source_ref: $this->mintSourceRef((int) $student->id, $semesterId),
            financial_effect: FinancialEffect::Debit,
            obligation_type: FinanceCharge::TYPE_TUITION_TERM,
            facts: $facts,
        ));
    }

    public function mintSourceRef(int $studentId, int $semesterId): string
    {
        return "tuition_term:student:{$studentId}:semester:{$semesterId}";
    }

    /**
     * Whether the student has a positive chargeable tuition term for this semester.
     * Used by generators to preserve zero-amount skip without calling intake.
     */
    public function isChargeable(Student $student, int $semesterId): bool
    {
        $termData = $this->timingResolver->getTuitionTermData($student, $semesterId);
        $amount = $termData['amount'] ?? null;

        return $amount !== null && (float) $amount > 0;
    }
}
