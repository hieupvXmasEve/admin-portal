<?php

declare(strict_types=1);

namespace App\Modules\Finance\Enums;

enum LifecycleDueExceptionReviewStatus: string
{
    case Open = 'open';
    case Acknowledged = 'acknowledged';
    case KeptAsDebt = 'kept_as_debt';
    case RoutedToSettlement = 'routed_to_settlement';
    case CancelRequested = 'cancel_requested';
    case Resolved = 'resolved';
    case Ignored = 'ignored';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Chờ xử lý',
            self::Acknowledged => 'Đã xem xét',
            self::KeptAsDebt => 'Giữ nợ',
            self::RoutedToSettlement => 'Chuyển quyết toán',
            self::CancelRequested => 'Đang hủy DNG',
            self::Resolved => 'Đã xử lý xong',
            self::Ignored => 'Bỏ qua',
        };
    }
}
