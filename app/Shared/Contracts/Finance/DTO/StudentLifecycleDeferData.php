<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Finance\DTO;

final readonly class StudentLifecycleDeferData
{
    /**
     * @param  list<int>  $courseRegistrationIds
     * @param  list<int>  $egcChargeIds
     */
    public function __construct(
        public int $studentActionLogId,
        public int $studentId,
        public int $semesterId,
        public ?int $appliesUntilSemesterId,
        public string $scopeType,
        public string $feePolicy,
        public ?float $preserveAmount,
        public ?string $signedAt,
        public int $changedByUserId,
        public array $courseRegistrationIds,
        public array $egcChargeIds,
        public bool $isEgcDefer,
    ) {}
}
