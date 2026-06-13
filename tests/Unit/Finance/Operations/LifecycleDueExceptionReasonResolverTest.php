<?php

declare(strict_types=1);

use App\Models\Student;
use App\Modules\Finance\Enums\LifecycleDueExceptionReason;
use App\Modules\Finance\Support\LifecycleDueExceptionReasonResolver;

it('maps lifecycle exception reasons correctly', function () {
    expect(LifecycleDueExceptionReasonResolver::resolve(null))
        ->toBe(LifecycleDueExceptionReason::MissingStudent);

    expect(LifecycleDueExceptionReasonResolver::resolve(new Student(['status' => 'deferred'])))
        ->toBe(LifecycleDueExceptionReason::Deferred);

    expect(LifecycleDueExceptionReasonResolver::resolve(new Student(['status' => 'dropout'])))
        ->toBe(LifecycleDueExceptionReason::Dropout);

    expect(LifecycleDueExceptionReasonResolver::resolve(new Student(['status' => 'dropout_transfer'])))
        ->toBe(LifecycleDueExceptionReason::DropoutTransfer);

    expect(LifecycleDueExceptionReasonResolver::resolve(new Student(['status' => 'pending'])))
        ->toBe(LifecycleDueExceptionReason::InactiveOrNonFinancial);
});
