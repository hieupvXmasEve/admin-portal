<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Finance;

use App\Shared\Contracts\Finance\DTO\FinanceCancellationOperationRequestResult;

interface FinanceCancellationOperationRequestContract
{
    /** @param array<string, mixed> $payload */
    public function request(
        string $sourceSystem,
        string $sourceKind,
        string $sourceRef,
        string $obligationType,
        string $unpaidVoidReason,
        string $paidVoidReason,
        ?int $actorUserId,
        array $payload,
    ): FinanceCancellationOperationRequestResult;
}
