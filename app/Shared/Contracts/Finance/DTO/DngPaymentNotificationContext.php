<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Finance\DTO;

final readonly class DngPaymentNotificationContext
{
    public function __construct(
        public string $studentName,
        public string $studentCode,
        public string $semesterCode,
        public string $programName,
        public string $invoiceCode,
        public string $amountFormatted,
        public ?string $dueDate,
    ) {}
}
