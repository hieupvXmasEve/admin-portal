<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Finance\DTO;

final readonly class FinanceCancellationOperationRequestResult
{
    public function __construct(public int $operationId, public string $status) {}
}
