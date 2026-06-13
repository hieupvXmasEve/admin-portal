<?php

declare(strict_types=1);

namespace App\Modules\Finance\Enums;

enum LifecycleDueExceptionResolutionAction: string
{
    case Acknowledge = 'acknowledge';
    case KeepAsDebt = 'keep_as_debt';
    case RouteToSettlement = 'route_to_settlement';
    case CancelDng = 'cancel_dng';
    case CancelDngAndVoidLinkedCharge = 'cancel_dng_and_void_linked_charge';

    public function label(): string
    {
        return match ($this) {
            self::Acknowledge => 'Ghi nhận',
            self::KeepAsDebt => 'Giữ nợ',
            self::RouteToSettlement => 'Chuyển quyết toán',
            self::CancelDng => 'Hủy DNG',
            self::CancelDngAndVoidLinkedCharge => 'Hủy DNG và void phí liên kết',
        };
    }

    public function isDestructive(): bool
    {
        return in_array($this, [self::CancelDng, self::CancelDngAndVoidLinkedCharge], true);
    }
}
