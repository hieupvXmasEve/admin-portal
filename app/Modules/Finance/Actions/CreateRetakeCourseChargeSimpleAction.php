<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions;

use App\Shared\Contracts\Academic\AcademicFinanceChargeSourceGateway;
use App\Shared\Contracts\Academic\DTO\AcademicChargeHandoffResult;

/**
 * Ensure a retake fee debit exists for an approved registration via intake.
 *
 * Finance delegates Academic source validation and state transition to the
 * Academic contract; Finance remains the owner of the materialized debit.
 */
class CreateRetakeCourseChargeSimpleAction
{
    public function __construct(
        private readonly AcademicFinanceChargeSourceGateway $academicSources,
    ) {}

    /**
     * @param  array{
     *   registration_id: int,
     *   charge_type?: string,
     *   amount?: float,
     *   description?: string,
     * }  $data
     */
    public function handle(array $data): AcademicChargeHandoffResult
    {
        return $this->academicSources->createRetakeCharge(
            (int) $data['registration_id'],
            (int) auth()->id(),
            $data['description'] ?? null,
        );
    }
}
