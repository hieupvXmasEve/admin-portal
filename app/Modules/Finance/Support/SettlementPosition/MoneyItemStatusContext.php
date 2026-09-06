<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support\SettlementPosition;

use DateTimeInterface;

/**
 * Explicit inputs for derived money-item status. Not persisted.
 */
final readonly class MoneyItemStatusContext
{
    public const REVIEWING = 'reviewing';

    public const ADJUSTED = 'adjusted';

    public const COMPLETED = 'completed';

    public const PROCESSING = 'processing';

    public const OVERDUE = 'overdue';

    public const AWAITING_PAYMENT = 'awaiting_payment';

    public function __construct(
        public SettlementPosition $position,
        public bool $hasSurplus = false,
        public bool $dngHoldingThisItem = false,
        public bool $dngNeedsReview = false,
        public bool $obligationCancelled = false,
        public bool $chargeVoid = false,
        public ?DateTimeInterface $dueDate = null,
    ) {}
}
