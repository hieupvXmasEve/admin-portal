<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support;

use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use DateTimeInterface;

/**
 * Invoice due_date is the single staff-chosen due date. Reminders may fall
 * back to the DNG request date only when no linked invoice date exists.
 */
final class StaffChosenDueDate
{
    public static function label(?DateTimeInterface $dueDate): string
    {
        return $dueDate?->format('d/m/Y') ?? '';
    }

    public static function labelForDngRequest(DngPaymentRequest $dngRequest): string
    {
        foreach ($dngRequest->reservationTargets as $target) {
            $due = $target->invoiceLine?->invoice?->due_date;
            if ($due !== null) {
                return self::label($due);
            }
        }

        return self::label($dngRequest->due_date);
    }
}
