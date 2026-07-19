<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support;

use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Shared\Contracts\Academic\AcademicPeriodReader;
use App\Shared\Contracts\Academic\ProgramEnrollmentReader;
use App\Shared\Contracts\Finance\DngPaymentNotificationContextReader;
use App\Shared\Contracts\Finance\DTO\DngPaymentNotificationContext;
use App\Shared\Contracts\StudentRegistry\StudentReferenceReader;

final readonly class EloquentDngPaymentNotificationContextReader implements DngPaymentNotificationContextReader
{
    public function __construct(
        private StudentReferenceReader $studentReferences,
        private ProgramEnrollmentReader $programEnrollments,
        private AcademicPeriodReader $academicPeriods,
    ) {}

    public function find(int $dngPaymentRequestId): ?DngPaymentNotificationContext
    {
        $request = DngPaymentRequest::query()->find($dngPaymentRequestId);
        if ($request === null) {
            return null;
        }

        $student = $this->studentReferences->find((int) $request->student_id);
        $enrollment = $this->programEnrollments->forStudentId((int) $request->student_id);
        $period = $request->semester_id === null
            ? null
            : $this->academicPeriods->find((int) $request->semester_id);

        return new DngPaymentNotificationContext(
            studentName: $student?->fullName ?? '',
            studentCode: (string) $request->student_code,
            semesterCode: $period?->code ?? '',
            programName: $enrollment->programName ?? '',
            invoiceCode: (string) $request->item_id,
            amountFormatted: number_format((float) $request->amount, 0, ',', '.').' VNĐ',
            dueDate: $request->due_date?->format('d/m/Y'),
        );
    }
}
