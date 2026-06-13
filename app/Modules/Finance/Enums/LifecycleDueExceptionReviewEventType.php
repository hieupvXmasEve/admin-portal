<?php

declare(strict_types=1);

namespace App\Modules\Finance\Enums;

enum LifecycleDueExceptionReviewEventType: string
{
    case Detected = 'detected';
    case Acknowledged = 'acknowledged';
    case KeptAsDebt = 'kept_as_debt';
    case RoutedToSettlement = 'routed_to_settlement';
    case CancelRequested = 'cancel_requested';
    case CancelSucceeded = 'cancel_succeeded';
    case CancelFailed = 'cancel_failed';
    case VoidRequested = 'void_requested';
    case VoidSucceeded = 'void_succeeded';
    case VoidFailed = 'void_failed';

    public function label(): string
    {
        return match ($this) {
            self::Detected => 'Phát hiện ngoại lệ',
            self::Acknowledged => 'Ghi nhận',
            self::KeptAsDebt => 'Giữ nợ',
            self::RoutedToSettlement => 'Chuyển quyết toán',
            self::CancelRequested => 'Yêu cầu hủy DNG',
            self::CancelSucceeded => 'Hủy DNG thành công',
            self::CancelFailed => 'Hủy DNG thất bại',
            self::VoidRequested => 'Yêu cầu void phí',
            self::VoidSucceeded => 'Void phí thành công',
            self::VoidFailed => 'Void phí thất bại',
        };
    }
}
