<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support\SettlementPosition;

final readonly class SettlementPositionShadowMismatch
{
    /**
     * @param  array<string, int|string|null>  $evidence
     */
    public function __construct(
        public string $kind,
        public bool $explained,
        public string $issue_code,
        public ?string $finance_invariant_code,
        public string $snapshot_version,
        public array $evidence,
    ) {}
}
