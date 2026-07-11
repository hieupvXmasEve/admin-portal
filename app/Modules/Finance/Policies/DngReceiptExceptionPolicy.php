<?php

declare(strict_types=1);

namespace App\Modules\Finance\Policies;

use App\Models\Campus;
use App\Models\User;
use App\Modules\Finance\Models\DngReceiptException;

class DngReceiptExceptionPolicy
{
    public function resolve(User $user, DngReceiptException $exception): bool
    {
        if (! $user->can('resolve_finance_dng_receipt_exceptions')) {
            return false;
        }

        $request = $exception->dngPaymentRequest()->with('student:id,campus_id')->first();
        $campus = app()->bound('campus') ? app('campus') : null;

        return ! $campus instanceof Campus
            || $request === null
            || $request->student?->campus_id === $campus->id
            || $user->can('view_finance_all_campus');
    }
}
