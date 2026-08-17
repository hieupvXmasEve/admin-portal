<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support;

use App\Models\Student;
use App\Modules\Finance\Enums\LifecycleDueExceptionReason;
use App\Shared\Contracts\Academic\StudentLifecycleStatusReader;

final class LifecycleDueExceptionReasonResolver
{
    /**
     * Resolves through the live enrollment projection, matching
     * {@see LifecycleDueItemPredicate::isLifecycleException()} — the gate and
     * the persisted reason must derive from the same status, or a row is
     * admitted as an exception for one reason and recorded with another.
     */
    public static function resolve(?Student $student): LifecycleDueExceptionReason
    {
        // An unpersisted model (no id) cannot have a program_enrollments row;
        // its in-memory `status` attribute is all there is to go on.
        if ($student === null || $student->id === null) {
            return self::forStatus($student?->status);
        }

        $status = app(StudentLifecycleStatusReader::class)->statusesFor([(int) $student->id])[(int) $student->id] ?? null;

        return self::forStatus($status);
    }

    public static function forStatus(?string $status): LifecycleDueExceptionReason
    {
        return match ($status) {
            'deferred' => LifecycleDueExceptionReason::Deferred,
            'dropout' => LifecycleDueExceptionReason::Dropout,
            'dropout_transfer' => LifecycleDueExceptionReason::DropoutTransfer,
            null => LifecycleDueExceptionReason::MissingStudent,
            default => LifecycleDueExceptionReason::InactiveOrNonFinancial,
        };
    }

    public static function recommendedAction(LifecycleDueExceptionReason $reason): string
    {
        return match ($reason) {
            LifecycleDueExceptionReason::Deferred => 'Xem xét chính sách bảo lưu trước khi nhắc nợ hoặc hủy DNG',
            LifecycleDueExceptionReason::Dropout => 'Rà soát quyết toán thôi học và nợ còn lại',
            LifecycleDueExceptionReason::DropoutTransfer => 'Rà soát quyết toán chuyển trường trước khi ghi nợ',
            LifecycleDueExceptionReason::MissingStudent => 'Sửa dữ liệu liên kết sinh viên trước khi xử lý tài chính',
            LifecycleDueExceptionReason::InactiveOrNonFinancial => 'Xem xét từng trường hợp theo trạng thái học vụ',
        };
    }
}
