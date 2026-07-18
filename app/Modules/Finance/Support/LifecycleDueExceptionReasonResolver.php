<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support;

use App\Models\Student;
use App\Modules\Finance\Enums\LifecycleDueExceptionReason;

final class LifecycleDueExceptionReasonResolver
{
    public static function resolve(?Student $student): LifecycleDueExceptionReason
    {
        return self::forStatus($student?->status);
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
