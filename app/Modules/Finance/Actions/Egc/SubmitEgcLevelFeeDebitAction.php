<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions\Egc;

use App\Modules\Finance\Models\FinanceCharge;
use App\Shared\Contracts\Finance\DTO\FinanceIntakeData;
use App\Shared\Contracts\Finance\DTO\FinanceIntakeResult;
use App\Shared\Contracts\Finance\Enums\FinancialEffect;
use App\Shared\Contracts\Finance\FinanceIntakeContract;
use RuntimeException;

/**
 * Finance-owned egc_level_fee intake client used by Batch Studio EGC generation
 * and legacy batch charge generation.
 *
 * Mints source_ref, submits pricing + generation facts only (never amount), and
 * lets FinanceIntakeContract price + materialize the debit read model.
 */
class SubmitEgcLevelFeeDebitAction
{
    public const SOURCE_SYSTEM = 'finance';

    public const SOURCE_KIND_BATCH_STUDIO = 'batch_studio';

    public const SOURCE_KIND_EGC_BATCH = 'egc_batch';

    public const SOURCE_KIND_LEGACY_EGC = 'legacy_egc';

    public const GENERATION_MODE_FRESH = 'fresh';

    public const GENERATION_MODE_REISSUE = 'reissue';

    public const GENERATION_MODE_DEFERRED = 'deferred';

    public const GENERATION_MODE_BATCH = 'batch';

    public function __construct(
        private readonly FinanceIntakeContract $intake,
    ) {}

    /**
     * @param  array{
     *     source_kind?: string,
     *     due_date?: string|null,
     *     invoice_id?: int|null,
     *     description?: string|null,
     *     block_number?: int|null,
     *     is_retake?: bool,
     *     generation_mode?: string,
     *     relevel?: bool,
     *     carry_forward?: bool,
     * }  $options
     */
    public function handle(
        int $studentId,
        int $semesterId,
        int $levelNumber,
        array $options = [],
    ): FinanceIntakeResult {
        $sourceKind = $options['source_kind'] ?? self::SOURCE_KIND_BATCH_STUDIO;

        if (! in_array($sourceKind, [
            self::SOURCE_KIND_BATCH_STUDIO,
            self::SOURCE_KIND_EGC_BATCH,
            self::SOURCE_KIND_LEGACY_EGC,
        ], true)) {
            throw new RuntimeException("Unsupported egc_level_fee source_kind [{$sourceKind}].");
        }

        $generationMode = $options['generation_mode'] ?? self::GENERATION_MODE_FRESH;
        $description = $options['description'] ?? "EGC Level {$levelNumber} Fee";

        $facts = [
            'student_id' => $studentId,
            'semester_id' => $semesterId,
            'level_number' => $levelNumber,
            'description' => $description,
            'generation_mode' => $generationMode,
            'is_retake' => (bool) ($options['is_retake'] ?? false),
            'relevel' => (bool) ($options['relevel'] ?? false),
            'carry_forward' => (bool) ($options['carry_forward'] ?? false),
        ];

        if (isset($options['block_number']) && is_numeric($options['block_number'])) {
            $facts['block_number'] = (int) $options['block_number'];
        }

        if (isset($options['due_date']) && is_string($options['due_date']) && $options['due_date'] !== '') {
            $facts['due_date'] = $options['due_date'];
        }

        if (isset($options['invoice_id']) && is_numeric($options['invoice_id']) && (int) $options['invoice_id'] > 0) {
            $facts['invoice_id'] = (int) $options['invoice_id'];
        }

        return $this->intake->request(new FinanceIntakeData(
            source_system: self::SOURCE_SYSTEM,
            source_kind: $sourceKind,
            source_ref: $this->mintSourceRef($studentId, $semesterId, $levelNumber),
            financial_effect: FinancialEffect::Debit,
            obligation_type: FinanceCharge::TYPE_EGC_LEVEL_FEE,
            facts: $facts,
        ));
    }

    public function mintSourceRef(int $studentId, int $semesterId, int $levelNumber): string
    {
        return "egc_level_fee:student:{$studentId}:semester:{$semesterId}:level:{$levelNumber}";
    }
}
