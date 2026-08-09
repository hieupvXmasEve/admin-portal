<?php

declare(strict_types=1);

namespace App\Modules\StudentRegistry\Actions;

use App\Models\Student;
use App\Shared\Contracts\Finance\StudentFinanceChargeExistenceReader;
use App\Shared\Contracts\Finance\StudentInvoiceExistenceReader;
use App\Shared\Contracts\Finance\StudentPaymentExistenceReader;
use App\Shared\Contracts\StudentRegistry\DTO\StudentReference;
use RuntimeException;

final class RequireRevocableStudentIdentityAction
{
    private const ACTIVITY_BLOCK_MESSAGE = 'This student already has academic or financial activity and cannot be revoked. '
        .'Use the Withdraw process to remove a student who has already studied.';

    /** @var list<string> */
    private const DOWNSTREAM_ACTIVITY_RELATIONS = [
        'courseRegistrations', 'enrollments', 'academicRecords', 'attendances',
        'gpaCalculations', 'academicStandings', 'academicHolds', 'programChangeRequests',
        'academicProgressionEvents', 'actionLogs', 'ieltsCertificates', 'egcProgress',
        'deferCases', 'voucherApplications',
        'dngPaymentRequests', 'goldTransactions', 'wallet', 'scholarshipAward',
        'clubMemberships', 'formResponses',
    ];

    /** @param array{student_id: int} $data */
    public static function run(array $data): StudentReference
    {
        $student = Student::query()->lockForUpdate()->find($data['student_id']);

        if ($student === null) {
            throw new RuntimeException('This application has no linked student to revoke.');
        }

        if (app(StudentFinanceChargeExistenceReader::class)->hasAnyChargeFor((int) $student->id)) {
            throw new RuntimeException(self::ACTIVITY_BLOCK_MESSAGE);
        }

        if (app(StudentPaymentExistenceReader::class)->hasAnyPaymentFor((int) $student->id)) {
            throw new RuntimeException(self::ACTIVITY_BLOCK_MESSAGE);
        }

        if (app(StudentInvoiceExistenceReader::class)->hasAnyInvoiceFor((int) $student->id)) {
            throw new RuntimeException(self::ACTIVITY_BLOCK_MESSAGE);
        }

        foreach (self::DOWNSTREAM_ACTIVITY_RELATIONS as $relation) {
            if ($student->{$relation}()->exists()) {
                throw new RuntimeException(self::ACTIVITY_BLOCK_MESSAGE);
            }
        }

        if ($student->user?->last_login_at !== null) {
            throw new RuntimeException(self::ACTIVITY_BLOCK_MESSAGE);
        }

        return new StudentReference(
            id: (int) $student->id,
            studentCode: (string) $student->student_id,
            fullName: (string) $student->full_name,
            campusId: (int) $student->campus_id,
            email: $student->email,
            address: $student->current_address_line ?? $student->address,
            nationalId: $student->national_id,
            userId: $student->user_id === null ? null : (int) $student->user_id,
        );
    }
}
