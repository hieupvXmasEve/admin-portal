<?php

declare(strict_types=1);

namespace App\Modules\Finance\Enums;

enum LifecycleDueExceptionReason: string
{
    case Deferred = 'deferred';
    case Dropout = 'dropout';
    case DropoutTransfer = 'dropout_transfer';
    case InactiveOrNonFinancial = 'inactive_or_non_financial';
    case MissingStudent = 'missing_student';

    public function label(): string
    {
        return match ($this) {
            self::Deferred => 'Bảo lưu',
            self::Dropout => 'Thôi học',
            self::DropoutTransfer => 'Chuyển trường',
            self::InactiveOrNonFinancial => 'Không thuộc trạng thái tài chính',
            self::MissingStudent => 'Thiếu sinh viên liên kết',
        };
    }
}
