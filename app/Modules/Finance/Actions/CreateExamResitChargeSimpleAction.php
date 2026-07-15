<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions;

use App\Shared\Contracts\Academic\AcademicFinanceChargeSourceGateway;
use App\Shared\Contracts\Academic\DTO\AcademicChargeHandoffResult;

/**
 * Ensure an exam resit fee debit exists for an HQ-pending attempt via intake.
 *
 * Finance delegates Academic source validation and state transition to the
 * Academic contract; Finance remains the owner of the materialized debit.
 */
class CreateExamResitChargeSimpleAction
{
    public function __construct(
        private readonly AcademicFinanceChargeSourceGateway $academicSources,
    ) {}

    /**
     * @param  array{
     *   attempt_id: int,
     *   amount?: float|null,
     *   due_date?: string|null,
     * }  $data
     */
    public function handle(array $data): AcademicChargeHandoffResult
    {
        return $this->academicSources->createExamResitCharge(
            (int) $data['attempt_id'],
            (int) auth()->id(),
            $data['due_date'] ?? null,
        );
    }
}
