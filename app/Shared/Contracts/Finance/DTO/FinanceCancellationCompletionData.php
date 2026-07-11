<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Finance\DTO;

final readonly class FinanceCancellationCompletionData
{
    /** @param array<string, mixed> $resultPayload */
    public function __construct(
        public string $sourceSystem,
        public string $sourceKind,
        public string $sourceRef,
        public string $obligationType,
        public array $resultPayload,
    ) {}
}
