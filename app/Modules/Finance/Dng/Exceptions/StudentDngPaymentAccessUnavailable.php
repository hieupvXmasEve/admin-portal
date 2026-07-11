<?php

declare(strict_types=1);

namespace App\Modules\Finance\Dng\Exceptions;

use RuntimeException;

final class StudentDngPaymentAccessUnavailable extends RuntimeException
{
    public const CODE = 'DNG_PAYMENT_ACCESS_UNAVAILABLE';

    public const MESSAGE = 'Thanh toán DNG hiện chưa khả dụng cho khoản phí đã chọn.';
}
